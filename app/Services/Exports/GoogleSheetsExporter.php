<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Infrastructure\Integrations\GoogleSheets;
use App\Models\Export;
use App\Models\Integration;
use Illuminate\Support\Collection;
use RuntimeException;

/** Appends rows to one spreadsheet the app created in the connected Google account. */
class GoogleSheetsExporter implements Exporter
{
    private const HEADERS = ['Date', 'Number', 'Supplier', 'Tax ID', 'Category', 'Net', 'Tax', 'Total', 'Currency', 'Due', 'Exported at'];

    public function __construct(private readonly GoogleSheets $google) {}

    public function export(Export $export, Collection $documents): ExportResult
    {
        $integration = Integration::query()->where('provider', Integration::GOOGLE)->first();
        if ($integration === null) {
            throw new RuntimeException('Google is not connected');
        }
        $token = $this->google->accessToken($integration);

        $spreadsheet = $integration->setting('spreadsheet');
        if (! is_array($spreadsheet)) {
            $spreadsheet = $this->google->createSpreadsheet($token, 'Invoice Extractor: expenses', 'Documents');
            $this->google->append($token, $spreadsheet['id'], $spreadsheet['sheet'], [self::HEADERS]);
            $integration->update(['settings' => [...($integration->settings ?? []), 'spreadsheet' => $spreadsheet]]);
        }

        $stamp = now()->format('Y-m-d H:i');
        $rows = $documents->map(function ($document) use ($stamp) {
            $r = new ExportRow($document);

            return [
                $r->issueDate(), $r->number(), $r->vendor(), $r->taxId(), $r->category('en'),
                $r->amount($document->subtotal), $r->amount($document->tax_amount), $r->amount($document->total),
                (string) $document->currency, $r->dueDate(), $stamp,
            ];
        })->all();

        $this->google->append($token, (string) $spreadsheet['id'], (string) $spreadsheet['sheet'], array_values($rows));

        return new ExportResult(externalUrl: (string) $spreadsheet['url']);
    }
}
