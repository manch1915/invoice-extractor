<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentRowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'filename' => $this->original_filename,
            'doc_type' => $this->doc_type,
            'vendor_name' => $this->vendor_name,
            'document_number' => $this->document_number,
            'issue_date' => $this->issue_date?->toDateString(),
            'currency' => $this->currency,
            'total' => $this->total,
            'confidence' => $this->confidence,
            'engine' => $this->engine,
            'auto_approved' => $this->auto_approved,
            'category' => $this->whenLoaded('category', fn () => $this->category ? CategoryResource::make($this->category)->resolve() : null),
            'issues_count' => $this->whenCounted('openIssues'),
            'thumbnail' => $this->page_count > 0 ? route('documents.page', [$this->id, 1]) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'error' => $this->error,
        ];
    }
}
