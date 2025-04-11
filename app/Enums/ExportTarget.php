<?php

declare(strict_types=1);

namespace App\Enums;

enum ExportTarget: string
{
    case Xlsx = 'xlsx';
    case OneC = 'csv_1c';
    case GoogleSheets = 'google_sheets';
    case Xero = 'xero';

    public function producesFile(): bool
    {
        return $this === self::Xlsx || $this === self::OneC;
    }
}
