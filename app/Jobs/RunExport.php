<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Export;
use App\Services\Exports\ExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly string $exportId)
    {
        $this->onQueue('exports');
    }

    public function handle(ExportService $exports): void
    {
        $export = Export::query()->find($this->exportId);
        if ($export !== null) {
            $exports->run($export);
        }
    }
}
