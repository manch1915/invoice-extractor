<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DocumentSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentsRequest;
use App\Models\Document;
use App\Models\DocumentField;
use App\Models\LineItem;
use App\Services\Documents\DocumentIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Upload from other systems and read the checked result back.
 */
class DocumentApiController extends Controller
{
    public function store(UploadDocumentsRequest $request, DocumentIngestor $ingestor): JsonResponse
    {
        abort_unless($request->user()?->tokenCan('documents:write'), 403);

        $documents = array_map(
            fn ($file) => $ingestor->fromUpload($file, DocumentSource::Api, $request->user()),
            Arr::wrap($request->file('files', [])),
        );

        return response()->json([
            'data' => array_map(fn (Document $d) => ['id' => $d->id, 'status' => $d->status->value, 'url' => route('api.documents.show', $d->id)], $documents),
        ], 202);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        abort_unless($request->user()?->tokenCan('documents:read'), 403);
        $document->load(['fields', 'lineItems', 'openIssues', 'category']);

        return response()->json(['data' => [
            'id' => $document->id,
            'status' => $document->status->value,
            'type' => $document->doc_type,
            'fields' => $document->fields->mapWithKeys(fn (DocumentField $f) => [$f->key->value => [
                'value' => $f->value,
                'confidence' => $f->confidence,
                'checked_by_person' => $f->status->isHumanChecked(),
            ]]),
            'line_items' => $document->lineItems->map(fn (LineItem $i) => [
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price !== null ? $i->unit_price / 100 : null,
                'amount' => $i->amount !== null ? $i->amount / 100 : null,
            ]),
            'category' => $document->category?->code,
            'issues' => $document->openIssues->map(fn ($i) => ['code' => $i->code->value, 'severity' => $i->severity->value, 'field' => $i->field?->value]),
            'review_url' => route('documents.show', $document->id),
        ]]);
    }
}
