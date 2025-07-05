<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Enums\DocumentStatus;
use App\Enums\ExportStatus;
use App\Enums\ExportTarget;
use App\Jobs\RunExport;
use App\Models\Document;
use App\Models\Export;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExportService
{
    public function __construct(
        private readonly Container $container,
        private readonly AuditLogger $audit,
    ) {}

    /** @param  list<string>  $documentIds */
    public function start(ExportTarget $target, array $documentIds, User $user): Export
    {
        $documents = Document::query()
            ->whereKey($documentIds)
            ->whereIn('status', [DocumentStatus::Ready, DocumentStatus::Exported])
            ->pluck('id');
        if ($documents->isEmpty()) {
            throw ValidationException::withMessages(['documents' => __('exports.nothing_ready')]);
        }

        $export = Export::create([
            'target' => $target,
            'status' => ExportStatus::Queued,
            'document_count' => $documents->count(),
            'user_id' => $user->id,
        ]);
        $export->documents()->attach($documents->all());
        $this->audit->record('export.started', $export, ['target' => $target->value, 'documents' => $documents->count()], $user);

        RunExport::dispatch($export->id);

        return $export;
    }

    public function run(Export $export): void
    {
        $export->update(['status' => ExportStatus::Running]);
        $documents = $export->documents()->with(['category', 'lineItems'])->orderBy('issue_date')->get();

        try {
            $result = $this->exporter($export->target)->export($export, $documents);
        } catch (Throwable $e) {
            $export->update(['status' => ExportStatus::Failed, 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
            $this->audit->record('export.failed', $export, ['error' => $e->getMessage()]);

            return;
        }

        DB::transaction(function () use ($export, $result, $documents) {
            foreach ($result->externalIds as $documentId => $externalId) {
                $export->documents()->updateExistingPivot($documentId, ['external_id' => $externalId]);
            }
            Document::query()->whereKey($documents->modelKeys())->update([
                'status' => DocumentStatus::Exported,
                'exported_at' => now(),
            ]);
            $export->update([
                'status' => ExportStatus::Done,
                'file_path' => $result->filePath,
                'external_url' => $result->externalUrl,
                'finished_at' => now(),
            ]);
        });
        $this->audit->record('export.finished', $export, ['documents' => $documents->count()]);
    }

    public function exporter(ExportTarget $target): Exporter
    {
        return $this->container->make(match ($target) {
            ExportTarget::Xlsx => XlsxExporter::class,
            ExportTarget::OneC => OneCCsvExporter::class,
            ExportTarget::GoogleSheets => GoogleSheetsExporter::class,
            ExportTarget::Xero => XeroExporter::class,
        });
    }
}
