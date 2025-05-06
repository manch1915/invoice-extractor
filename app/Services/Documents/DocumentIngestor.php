<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentSource;
use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Accepts a file from any channel (upload, mailbox, API), stores it and queues the reading.
 */
class DocumentIngestor
{
    public function __construct(
        private readonly Filesystem $disk,
        private readonly AuditLogger $audit,
    ) {}

    public function fromUpload(UploadedFile $file, DocumentSource $source, ?User $user): Document
    {
        return $this->ingest(
            (string) $file->get(),
            $file->getClientOriginalName(),
            (string) ($file->getMimeType() ?? 'application/octet-stream'),
            $source,
            $user,
        );
    }

    /** @param  array<string, string>|null  $meta */
    public function ingest(
        string $contents,
        string $filename,
        string $mime,
        DocumentSource $source,
        ?User $user = null,
        ?array $meta = null,
    ): Document {
        $id = (string) Str::ulid();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'bin';
        $path = "originals/{$id}.{$extension}";
        $this->disk->put($path, $contents);

        $document = new Document([
            'status' => DocumentStatus::Queued,
            'source' => $source,
            'source_meta' => $meta,
            'original_filename' => Str::limit(basename($filename), 250, ''),
            'mime_type' => $mime,
            'size' => strlen($contents),
            'storage_path' => $path,
            'sha256' => hash('sha256', $contents),
            'uploaded_by' => $user?->id,
        ]);
        // the id is known before saving so the stored file and the row share it
        $document->id = $id;
        $document->save();

        $this->audit->record('document.uploaded', $document, ['source' => $source->value, 'file' => $filename], $user);
        ProcessDocument::dispatch($document->id);

        return $document;
    }
}
