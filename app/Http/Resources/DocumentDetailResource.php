<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FieldKey;
use App\Models\Document;
use App\Models\DocumentField;
use App\Models\DocumentIssue;
use App\Models\DocumentPage;
use App\Models\LineItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $fields = $this->fields->keyBy(fn (DocumentField $f) => $f->key->value);

        return [
            ...DocumentRowResource::make($this->resource)->resolve(),
            'language' => $this->language,
            'text_source' => $this->text_source,
            'ocr_quality' => $this->ocr_quality,
            'processing_ms' => $this->processing_ms,
            'warnings' => $this->warnings ?? [],
            'category_source' => $this->category_source?->value,
            'suggested_category' => $this->suggested_category,
            'duplicate_of_id' => $this->duplicate_of_id,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reviewer' => $this->reviewer?->name,
            'original_url' => route('documents.original', $this->id),
            'mime_type' => $this->mime_type,
            'vendor' => $this->vendor ? [
                'id' => $this->vendor->id,
                'name' => $this->vendor->name,
                'tax_id' => $this->vendor->tax_id,
                'documents' => $this->vendor->documents()->count(),
            ] : null,
            'pages' => $this->pages->map(fn (DocumentPage $p) => [
                'number' => $p->number,
                'width' => $p->width,
                'height' => $p->height,
                'url' => route('documents.page', [$this->id, $p->number]),
                'words' => $p->words,
            ])->values(),
            'fields' => collect(FieldKey::cases())->map(function (FieldKey $key) use ($fields) {
                /** @var DocumentField|null $field */
                $field = $fields->get($key->value);

                return [
                    'key' => $key->value,
                    'value' => $field?->value,
                    'original_value' => $field?->original_value,
                    'raw' => $field?->raw,
                    'confidence' => $field !== null ? $field->confidence : 0,
                    'page' => $field?->page,
                    'bbox' => $field?->bbox,
                    'status' => $field?->status->value ?? 'review',
                ];
            })->values(),
            'line_items' => $this->lineItems->map(fn (LineItem $i) => [
                'id' => $i->id,
                'position' => $i->position,
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
                'amount' => $i->amount,
                'confidence' => $i->confidence,
                'page' => $i->page,
                'bbox' => $i->bbox,
            ])->values(),
            'issues' => $this->issues->sortBy('created_at')->map(fn (DocumentIssue $issue) => [
                'id' => $issue->id,
                'code' => $issue->code->value,
                'severity' => $issue->severity->value,
                'field' => $issue->field?->value,
                'context' => $issue->context ?? [],
                'resolved' => $issue->resolved_at !== null,
            ])->values(),
        ];
    }
}
