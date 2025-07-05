<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Models\Document;
use App\Models\Export;
use Illuminate\Support\Collection;

interface Exporter
{
    /** @param  Collection<int, Document>  $documents */
    public function export(Export $export, Collection $documents): ExportResult;
}
