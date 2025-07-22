<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DocumentSource;
use App\Enums\DocumentStatus;
use App\Enums\ExportTarget;
use App\Enums\IssueSeverity;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\Export;
use App\Models\User;
use App\Services\Documents\DocumentIngestor;
use App\Services\Documents\ReviewService;
use App\Services\Exports\ExportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Loads the generated sample documents through the real pipeline, then plays back a few weeks
 * of work: older documents get approved by the accountant, the latest ones wait for review.
 */
class SeedDemo extends Command
{
    protected $description = 'Load the sample invoices and receipts through the worker';

    protected $signature = 'demo:seed {--path= : folder with manifest.json and documents/} {--keep-recent=14 : days of documents left for review}';

    public function handle(DocumentIngestor $ingestor, ReviewService $review, ExportService $exports): int
    {
        $option = $this->option('path');
        $path = is_string($option) && $option !== '' ? $option : database_path('demo');
        $manifest = json_decode((string) file_get_contents("{$path}/manifest.json"), true, flags: JSON_THROW_ON_ERROR);
        $accountant = User::role(Role::Accountant->value)->firstOrFail();
        // read each document right away instead of handing it to the queue workers
        config(['queue.default' => 'sync']);

        // oldest first, so duplicates and "unusual amount" compare against what came before
        usort($manifest, fn (array $a, array $b) => strcmp($a['expected']['issue_date'], $b['expected']['issue_date']));

        $bar = $this->output->createProgressBar(count($manifest));
        foreach ($manifest as $entry) {
            $file = "{$path}/documents/{$entry['file']}";
            $mime = str_ends_with($file, '.pdf') ? 'application/pdf' : 'image/jpeg';

            try {
                $ingestor->ingest((string) file_get_contents($file), $entry['file'], $mime, DocumentSource::Demo, $accountant);
            } catch (Throwable $e) {
                $this->newLine();
                $this->warn("{$entry['file']}: {$e->getMessage()}");
            }

            $document = Document::query()->latest('id')->firstOrFail();
            $this->backdate($document, $entry['expected']['issue_date']);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->playBackReviews($review, $accountant, (int) $this->option('keep-recent'));
        $this->playBackExports($exports, $accountant);
        $this->backdateActivity();

        $counts = Document::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $this->table(['status', 'documents'], $counts->map(fn ($n, $s) => [$s, $n])->values()->all());

        return self::SUCCESS;
    }

    /** Documents arrive a day or three after they are issued. */
    private function backdate(Document $document, string $issued): void
    {
        $date = CarbonImmutable::parse($issued);
        $received = $date->isFuture() ? CarbonImmutable::now()->subDays(4) : $date->addDays(crc32($document->id) % 3 + 1)->setTime(9 + crc32($document->sha256) % 8, 17);
        $received = $received->isFuture() ? CarbonImmutable::now()->subHours(3) : $received;

        Document::query()->whereKey($document->id)->update([
            'created_at' => $received,
            'updated_at' => $received,
            'processed_at' => $received->addSeconds(12),
        ]);
    }

    /** Summer documents already went to 1C at the start of September, then August's to Excel. */
    private function playBackExports(ExportService $exports, User $accountant): void
    {
        $batches = [
            [ExportTarget::OneC, '2026-07-01', '2026-08-01', '2026-08-03 10:12'],
            [ExportTarget::Xlsx, '2026-08-01', '2026-09-01', '2026-09-02 16:40'],
        ];
        foreach ($batches as [$target, $from, $to, $when]) {
            $ids = array_values(Document::query()
                ->where('status', DocumentStatus::Ready)
                ->where('issue_date', '>=', $from)
                ->where('issue_date', '<', $to)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all());
            if ($ids === []) {
                continue;
            }
            $export = $exports->start($target, $ids, $accountant);
            Export::query()->whereKey($export->id)->update(['created_at' => $when, 'finished_at' => $when]);
            Document::query()->whereKey($ids)->update(['exported_at' => $when]);
        }
    }

    /**
     * The activity log was written just now; move each entry to when it would have happened.
     * Rule events are stamped with the approval recorded right after them in the same request.
     */
    private function backdateActivity(): void
    {
        $documents = Document::query()->get(['id', 'created_at', 'reviewed_at'])->keyBy('id');
        $exports = Export::query()->get(['id', 'created_at'])->keyBy('id');
        $next = null;

        foreach (AuditEvent::query()->orderByDesc('id')->get() as $event) {
            $document = $event->subject_type === 'Document' ? $documents->get($event->subject_id) : null;
            $export = $event->subject_type === 'Export' ? $exports->get($event->subject_id) : null;

            $when = match (true) {
                $document !== null && $event->action === 'document.approved' => $document->reviewed_at,
                $document !== null && $event->action === 'document.processed' => CarbonImmutable::parse($document->created_at)->addSeconds(12),
                $document !== null => $document->created_at,
                $export !== null => $export->created_at,
                default => $next,
            };
            if ($when !== null) {
                AuditEvent::query()->whereKey($event->id)->update(['created_at' => $when]);
                $next = $when;
            }
        }
    }

    /** Older documents without real problems were already checked by the accountant. */
    private function playBackReviews(ReviewService $review, User $accountant, int $keepRecentDays): void
    {
        $cutoff = CarbonImmutable::today()->subDays($keepRecentDays);

        $older = Document::query()
            ->where('status', DocumentStatus::NeedsReview)
            ->where('issue_date', '<', $cutoff->toDateString())
            ->whereDoesntHave('openIssues', fn ($q) => $q->where('severity', IssueSeverity::Blocking->value))
            ->get();

        foreach ($older as $document) {
            $seconds = 25 + crc32($document->id) % 70;
            $review->approve($document, $accountant, $seconds);
            Document::query()->whereKey($document->id)->update([
                'reviewed_at' => CarbonImmutable::parse($document->created_at)->addHours(2 + crc32($document->sha256) % 20),
            ]);
        }

        $this->info("Approved {$older->count()} older documents as the accountant.");
    }
}
