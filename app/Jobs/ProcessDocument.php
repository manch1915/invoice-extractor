<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Infrastructure\Worker\WorkerClient;
use App\Infrastructure\Worker\WorkerException;
use App\Models\Category;
use App\Models\Document;
use App\Services\Documents\ExtractionApplier;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly string $documentId)
    {
        $this->onQueue('extraction');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->documentId))->expireAfter(300)];
    }

    public function handle(WorkerClient $worker, ExtractionApplier $applier, Filesystem $disk): void
    {
        $document = Document::query()->find($this->documentId);
        if ($document === null) {
            return;
        }

        $document->update(['status' => DocumentStatus::Processing]);

        $categories = Category::query()
            ->orderBy('id')
            ->get(['code', 'name_en'])
            ->map(fn (Category $c) => ['code' => $c->code, 'name' => $c->name_en])
            ->all();

        try {
            $result = $worker->extract(
                $disk->readStream($document->storage_path) ?? '',
                $document->original_filename,
                array_values($categories),
            );
        } catch (WorkerException $e) {
            if (! $e->retryable) {
                $document->update(['status' => DocumentStatus::Failed, 'error' => $e->getMessage()]);

                return;
            }
            $document->update(['status' => DocumentStatus::Queued, 'error' => $e->getMessage()]);

            throw $e;
        }

        $applier->apply($document, $result);
    }

    public function failed(?Throwable $exception): void
    {
        Document::query()->whereKey($this->documentId)->update([
            'status' => DocumentStatus::Failed,
            'error' => $exception?->getMessage() ?? 'Processing failed',
        ]);
    }
}
