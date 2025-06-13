<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Domain\Money\Minor;
use App\Enums\CategorySource;
use App\Enums\DocumentStatus;
use App\Enums\FieldKey;
use App\Enums\FieldStatus;
use App\Enums\IssueSeverity;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentIssue;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Categories\CategoryLearner;
use App\Services\FieldNormalizer;
use App\Services\VendorMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Everything a reviewer can do to a document. Each action is audited and re-runs the checks.
 */
class ReviewService
{
    public function __construct(
        private readonly FieldNormalizer $normalizer,
        private readonly ExtractionApplier $applier,
        private readonly DocumentChecker $checker,
        private readonly VendorMatcher $vendors,
        private readonly CategoryLearner $learner,
        private readonly AuditLogger $audit,
    ) {}

    /** @param  list<float>|null  $bbox  where the reviewer picked the value on the page, if they did */
    public function updateField(Document $document, FieldKey $key, ?string $input, User $user, ?array $bbox = null, ?int $page = null): void
    {
        $this->ensureEditable($document);
        $value = $this->normalizer->normalize($key, $input);

        DB::transaction(function () use ($document, $key, $value, $user, $bbox, $page) {
            $field = $document->fields()->where('key', $key->value)->firstOrFail();
            $before = $field->value;
            $changes = [
                'value' => $value,
                'status' => $value === $field->original_value ? FieldStatus::Confirmed : FieldStatus::Corrected,
                'updated_by' => $user->id,
            ];
            if ($bbox !== null) {
                $changes += ['bbox' => $bbox, 'page' => $page];
            }
            $field->update($changes);
            if ($key === FieldKey::TaxAmount || $key === FieldKey::Total) {
                $this->recalculateDerivedSubtotal($document);
            }

            $this->applier->copyFieldsToColumns($document);
            if ($key === FieldKey::VendorName || $key === FieldKey::VendorTaxId) {
                $document->vendor_id = $this->vendors->match($document->vendor_name, $document->vendor_tax_id)?->id;
            }
            $this->refresh($document);

            $this->audit->record('field.updated', $document, ['field' => $key->value, 'from' => $before, 'to' => $value], $user);
        });
    }

    public function confirmField(Document $document, FieldKey $key, User $user): void
    {
        $this->ensureEditable($document);
        $field = $document->fields()->where('key', $key->value)->firstOrFail();
        if ($field->status->isHumanChecked()) {
            return;
        }
        $field->update(['status' => FieldStatus::Confirmed, 'updated_by' => $user->id]);
        $this->refresh($document);
        $this->audit->record('field.confirmed', $document, ['field' => $key->value, 'value' => $field->value], $user);
    }

    public function setCategory(Document $document, Category $category, User $user, bool $learn = true): void
    {
        $this->ensureEditable($document);
        $before = $document->category_id;
        $document->fill(['category_id' => $category->id, 'category_source' => CategorySource::User]);
        $this->refresh($document);
        if ($learn) {
            $this->learner->learn($document, $category, $user);
        }
        $this->audit->record('category.set', $document, ['from' => $before, 'to' => $category->id], $user);
    }

    /** "I looked at it and it is fine": the warning stays visible but no longer blocks. */
    public function resolveIssue(Document $document, DocumentIssue $issue, User $user): void
    {
        $this->ensureEditable($document);
        $issue->update(['resolved_at' => now(), 'resolved_by' => $user->id]);
        $this->refresh($document);
        $this->audit->record('issue.resolved', $document, ['issue' => $issue->code->value, 'field' => $issue->field?->value], $user);
    }

    public function approve(Document $document, User $user, int $secondsSpent = 0): void
    {
        $this->ensureEditable($document);
        $blocking = $document->openIssues()->where('severity', IssueSeverity::Blocking->value)->count();
        if ($blocking > 0) {
            throw ValidationException::withMessages(['document' => __('review.blocked', ['count' => $blocking])]);
        }

        DB::transaction(function () use ($document, $user, $secondsSpent) {
            $document->fields()
                ->whereNotIn('status', [FieldStatus::Confirmed->value, FieldStatus::Corrected->value])
                ->whereNotNull('value')
                ->update(['status' => FieldStatus::Confirmed->value, 'updated_by' => $user->id]);

            $document->update([
                'status' => $document->status === DocumentStatus::Exported ? DocumentStatus::Exported : DocumentStatus::Ready,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_seconds' => $document->review_seconds + max(0, min($secondsSpent, 3600)),
            ]);

            if ($document->category && $document->category_source !== CategorySource::VendorRule) {
                $this->learner->learn($document, $document->category, $user);
            }

            $this->audit->record('document.approved', $document, ['seconds' => $secondsSpent], $user);
        });
    }

    public function reject(Document $document, User $user, ?string $reason = null): void
    {
        $document->update(['status' => DocumentStatus::Rejected]);
        $this->audit->record('document.rejected', $document, ['reason' => $reason], $user);
    }

    public function reopen(Document $document, User $user): void
    {
        $document->update(['status' => DocumentStatus::NeedsReview, 'reviewed_at' => null, 'reviewed_by' => null]);
        $this->audit->record('document.reopened', $document, [], $user);
    }

    /**
     * Russian invoices often print only the total and "в т.ч. НДС"; the net amount was computed
     * from them, so it follows when a person corrects either number.
     */
    private function recalculateDerivedSubtotal(Document $document): void
    {
        $fields = $document->fields()->whereIn('key', [FieldKey::Subtotal->value, FieldKey::TaxAmount->value, FieldKey::Total->value])
            ->get()->keyBy(fn ($f) => $f->key->value);
        $subtotal = $fields->get(FieldKey::Subtotal->value);
        $tax = Minor::tryFromDecimal($fields->get(FieldKey::TaxAmount->value)?->value);
        $total = Minor::tryFromDecimal($fields->get(FieldKey::Total->value)?->value);

        if ($subtotal === null || $subtotal->raw !== null || $subtotal->status->isHumanChecked() || $tax === null || $total === null) {
            return;
        }
        $subtotal->update(['value' => Minor::toDecimal($total - $tax), 'status' => FieldStatus::Auto]);
    }

    private function refresh(Document $document): void
    {
        $document->unsetRelation('lineItems');
        $this->checker->check($document);
        $document->save();

        // an approved document goes back to the queue only if an edit broke something
        if ($document->status === DocumentStatus::Ready && $document->hasBlockingIssues()) {
            $document->update(['status' => DocumentStatus::NeedsReview, 'reviewed_at' => null]);
        }
    }

    private function ensureEditable(Document $document): void
    {
        if (! $document->status->isEditable()) {
            throw ValidationException::withMessages(['document' => __('review.not_editable')]);
        }
    }
}
