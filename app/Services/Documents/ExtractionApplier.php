<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Domain\Money\Minor;
use App\Domain\TaxId\TaxId;
use App\Enums\DocumentStatus;
use App\Enums\FieldKey;
use App\Enums\FieldStatus;
use App\Infrastructure\Worker\ExtractionResult;
use App\Models\Document;
use App\Services\AuditLogger;
use App\Services\Categories\CategoryResolver;
use App\Services\VendorMatcher;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

/**
 * Stores what the worker read: page images, fields with their boxes, line items,
 * then links the vendor, picks a category and runs the checks.
 */
class ExtractionApplier
{
    public function __construct(
        private readonly Filesystem $disk,
        private readonly VendorMatcher $vendors,
        private readonly CategoryResolver $categories,
        private readonly DocumentChecker $checker,
        private readonly AuditLogger $audit,
    ) {}

    public function apply(Document $document, ExtractionResult $result): Document
    {
        DB::transaction(function () use ($document, $result) {
            $this->storePages($document, $result);
            $this->storeFields($document, $result);
            $this->storeItems($document, $result);

            $document->fill([
                'doc_type' => $result->docType,
                'language' => $result->language,
                'engine' => $result->engine,
                'text_source' => $result->textSource,
                'ocr_quality' => $result->ocrQuality,
                'page_count' => count($result->pages),
                'processing_ms' => $result->durationMs,
                'warnings' => $result->warnings,
                'suggested_category' => $result->categoryCode,
                'confidence' => $this->overallConfidence($result),
                'error' => null,
                'processed_at' => now(),
            ]);
            $this->copyFieldsToColumns($document);

            $vendor = $this->vendors->match($document->vendor_name, $document->vendor_tax_id);
            $document->vendor_id = $vendor?->id;
            $document->setRelation('lineItems', $document->lineItems()->get());

            $choice = $this->categories->resolve($document, $result->categoryCode);
            $document->category_id = $choice?->category->id;
            $document->category_source = $choice?->source;

            $this->checker->check($document);
            $document->save();

            $status = $this->checker->decideStatus($document);
            $document->update([
                'status' => $status,
                'auto_approved' => $status === DocumentStatus::Ready,
            ]);

            $this->audit->record('document.processed', $document, [
                'engine' => $result->engine,
                'status' => $status->value,
            ]);
        });

        return $document->refresh();
    }

    private function storePages(Document $document, ExtractionResult $result): void
    {
        foreach ($document->pages as $old) {
            $this->disk->delete($old->getRawOriginal('image_path'));
        }
        $document->pages()->delete();

        foreach ($result->pages as $page) {
            $path = "pages/{$document->id}/{$page['number']}.webp";
            $this->disk->put($path, (string) base64_decode($page['image'], true));
            $document->pages()->create([
                'number' => $page['number'],
                'image_path' => $path,
                'width' => $page['width'],
                'height' => $page['height'],
                'words' => $page['words'],
            ]);
        }
    }

    private function storeFields(Document $document, ExtractionResult $result): void
    {
        $document->fields()->delete();
        foreach (FieldKey::cases() as $key) {
            $field = $result->field($key);
            $document->fields()->create([
                'key' => $key,
                'value' => $field->value,
                'original_value' => $field->value,
                'raw' => $field->raw,
                'confidence' => $field->confidence,
                'page' => $field->page,
                'bbox' => $field->bbox,
                // an optional field that is simply not on the document (no due date on a receipt) is not a doubt
                'status' => ($field->value !== null || $key->isKey())
                    && DocumentChecker::fieldNeedsReview($field->confidence, FieldStatus::Auto)
                    ? FieldStatus::NeedsReview
                    : FieldStatus::Auto,
            ]);
        }
    }

    private function storeItems(Document $document, ExtractionResult $result): void
    {
        $document->lineItems()->delete();
        foreach ($result->items as $position => $item) {
            $document->lineItems()->create([
                'position' => $position + 1,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => Minor::tryFromDecimal($item->unitPrice),
                'amount' => Minor::tryFromDecimal($item->amount),
                'confidence' => $item->confidence,
                'page' => $item->page,
                'bbox' => $item->bbox,
            ]);
        }
    }

    /** Keeps the typed columns (used for search, dashboards and exports) in step with the fields. */
    public function copyFieldsToColumns(Document $document): void
    {
        $values = $document->fields()->pluck('value', 'key');
        $get = fn (FieldKey $key): ?string => $values[$key->value] ?? null;

        $document->fill([
            'vendor_name' => $get(FieldKey::VendorName),
            'vendor_tax_id' => TaxId::normalize($get(FieldKey::VendorTaxId)),
            'buyer_name' => $get(FieldKey::BuyerName),
            'buyer_tax_id' => TaxId::normalize($get(FieldKey::BuyerTaxId)),
            'document_number' => $get(FieldKey::DocumentNumber),
            'issue_date' => $get(FieldKey::IssueDate),
            'due_date' => $get(FieldKey::DueDate),
            'currency' => $get(FieldKey::Currency),
            'subtotal' => Minor::tryFromDecimal($get(FieldKey::Subtotal)),
            'tax_rate' => $get(FieldKey::TaxRate),
            'tax_amount' => Minor::tryFromDecimal($get(FieldKey::TaxAmount)),
            'total' => Minor::tryFromDecimal($get(FieldKey::Total)),
        ]);
    }

    private function overallConfidence(ExtractionResult $result): float
    {
        $keys = array_filter(FieldKey::cases(), fn (FieldKey $k) => $k->isKey());
        $scores = array_map(fn (FieldKey $k) => $result->field($k)->confidence, $keys);

        return $scores === [] ? 0.0 : round(min($scores), 3);
    }
}
