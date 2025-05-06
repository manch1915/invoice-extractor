<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Domain\Validation\DocumentFacts;
use App\Domain\Validation\DocumentValidator;
use App\Domain\Validation\Issue;
use App\Enums\DocumentStatus;
use App\Enums\FieldStatus;
use App\Models\Document;
use App\Models\DocumentIssue;
use DateTimeImmutable;

/**
 * Re-runs the checks after any change and decides whether a person has to look at the document.
 */
class DocumentChecker
{
    public function __construct(private readonly DocumentValidator $validator) {}

    /** @return list<Issue> */
    public function check(Document $document): array
    {
        $issues = $this->validator->validate($this->facts($document));
        $this->sync($document, $issues);

        return $issues;
    }

    public function facts(Document $document): DocumentFacts
    {
        $document->loadMissing('lineItems');

        return new DocumentFacts(
            vendorName: $document->vendor_name,
            vendorTaxId: $document->vendor_tax_id,
            documentNumber: $document->document_number,
            issueDate: $document->issue_date ? DateTimeImmutable::createFromInterface($document->issue_date) : null,
            dueDate: $document->due_date ? DateTimeImmutable::createFromInterface($document->due_date) : null,
            currency: $document->currency,
            subtotal: $document->subtotal,
            taxRate: $document->tax_rate !== null ? (string) $document->tax_rate : null,
            taxAmount: $document->tax_amount,
            total: $document->total,
            itemAmounts: array_values($document->lineItems->map(fn ($i) => $i->amount)->all()),
            vendorHistory: $this->vendorHistory($document),
            duplicateIds: $this->duplicates($document),
            hasCategory: $document->category_id !== null,
            today: new DateTimeImmutable('today'),
        );
    }

    /** Needs a person if a check blocks it or a key field is not trustworthy enough. */
    public function decideStatus(Document $document): DocumentStatus
    {
        if ($document->hasBlockingIssues()) {
            return DocumentStatus::NeedsReview;
        }

        $threshold = (float) config('invoices.review_threshold');
        $doubtful = $document->fields()
            ->get()
            ->contains(fn ($f) => $f->key->isKey() && ! $f->status->isHumanChecked() && $f->confidence < $threshold);

        return $doubtful ? DocumentStatus::NeedsReview : DocumentStatus::Ready;
    }

    /** @param  list<Issue>  $issues */
    private function sync(Document $document, array $issues): void
    {
        $existing = $document->issues()->get()->keyBy(fn (DocumentIssue $i) => $i->key());
        $current = [];

        foreach ($issues as $issue) {
            $key = $issue->key();
            $current[$key] = true;
            $row = $existing->get($key);
            if ($row) {
                // keep a person's "this is fine" decision, refresh the numbers
                $row->update(['context' => $issue->context, 'severity' => $issue->severity]);

                continue;
            }
            $document->issues()->create([
                'code' => $issue->code,
                'severity' => $issue->severity,
                'field' => $issue->field,
                'context' => $issue->context,
            ]);
        }

        $stale = $existing->keys()->reject(fn (string $key) => isset($current[$key]));
        if ($stale->isNotEmpty()) {
            $document->issues()->whereIn('id', $existing->only($stale->all())->pluck('id'))->delete();
        }

        $duplicate = collect($issues)->first(fn (Issue $i) => $i->code->value === 'duplicate');
        $document->duplicate_of_id = $duplicate ? (string) $duplicate->context['original_id'] : null;
    }

    /** @return list<int> */
    private function vendorHistory(Document $document): array
    {
        if ($document->vendor_id === null) {
            return [];
        }

        return array_values(Document::query()
            ->where('vendor_id', $document->vendor_id)
            ->whereKeyNot($document->getKey())
            ->whereNotNull('total')
            ->where('currency', $document->currency)
            ->whereIn('status', [DocumentStatus::Ready, DocumentStatus::Exported, DocumentStatus::NeedsReview])
            ->pluck('total')
            ->map(fn ($t) => (int) $t)
            ->all());
    }

    /**
     * Earlier documents (ULIDs sort by creation) with the same file, or the same vendor, number and total.
     *
     * @return list<string>
     */
    private function duplicates(Document $document): array
    {
        $earlier = fn () => Document::query()->visible()->where('id', '<', $document->getKey());

        $ids = $earlier()->where('sha256', $document->sha256)->pluck('id');

        $vendorKnown = $document->vendor_id !== null || $document->vendor_tax_id !== null;
        if ($document->document_number !== null && $document->total !== null && $vendorKnown) {
            $ids = $ids->merge($earlier()
                ->where('document_number', $document->document_number)
                ->where('total', $document->total)
                ->where(fn ($q) => $q
                    ->when($document->vendor_id, fn ($q) => $q->orWhere('vendor_id', $document->vendor_id))
                    ->when($document->vendor_tax_id, fn ($q) => $q->orWhere('vendor_tax_id', $document->vendor_tax_id)))
                ->pluck('id'));
        }

        return array_values($ids->map(fn ($id) => (string) $id)->unique()->sort()->all());
    }

    public static function fieldNeedsReview(float $confidence, FieldStatus $status): bool
    {
        return ! $status->isHumanChecked() && $confidence < (float) config('invoices.review_threshold');
    }
}
