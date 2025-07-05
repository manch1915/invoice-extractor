<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Models\Export;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;

/**
 * CSV the way 1C's "load from a spreadsheet" processing expects it:
 * Windows-1251, semicolons, dd.mm.yyyy dates and decimal commas.
 */
class OneCCsvExporter implements Exporter
{
    private const COLUMNS = [
        'Дата', 'Номер', 'Вид документа', 'Контрагент', 'ИНН контрагента', 'Сумма', 'Ставка НДС',
        'Сумма НДС', 'Валюта', 'Статья затрат', 'Счет затрат', 'Срок оплаты', 'Комментарий',
    ];

    private const TYPES = [
        'invoice' => 'Счет на оплату',
        'waybill' => 'Товарная накладная',
        'act' => 'Акт выполненных работ',
        'receipt' => 'Кассовый чек',
    ];

    public function __construct(private readonly Filesystem $disk) {}

    public function export(Export $export, Collection $documents): ExportResult
    {
        $lines = [$this->line(self::COLUMNS)];
        foreach ($documents as $document) {
            $r = new ExportRow($document);
            $lines[] = $this->line([
                $r->issueDate('d.m.Y'),
                $r->number(),
                self::TYPES[$document->doc_type ?? 'invoice'] ?? self::TYPES['invoice'],
                $r->vendor(),
                $r->taxId(),
                $r->decimal($document->total, ','),
                $this->rate($document->tax_rate),
                $r->decimal($document->tax_amount, ','),
                $document->currency === 'RUB' ? 'руб.' : (string) $document->currency,
                $r->category('ru'),
                (string) $document->category?->account_1c,
                $r->dueDate('d.m.Y'),
                $document->original_filename,
            ]);
        }

        $csv = implode("\r\n", $lines)."\r\n";
        $path = "exports/{$export->id}.csv";
        $this->disk->put($path, (string) mb_convert_encoding($csv, 'Windows-1251', 'UTF-8'));

        return new ExportResult(filePath: $path);
    }

    private function rate(?string $rate): string
    {
        if ($rate === null) {
            return '';
        }
        $value = (float) $rate;

        return $value === 0.0 ? 'Без НДС' : rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',').'%';
    }

    /** @param  list<string>  $cells */
    private function line(array $cells): string
    {
        return implode(';', array_map(function (string $cell): string {
            $cell = str_replace(["\r", "\n"], ' ', $cell);

            // a leading = + - @ would be run as a formula when the file is opened in a spreadsheet
            if ($cell !== '' && in_array($cell[0], ['=', '+', '-', '@'], true)) {
                $cell = "'".$cell;
            }

            return preg_match('/[;"]/', $cell) ? '"'.str_replace('"', '""', $cell).'"' : $cell;
        }, $cells));
    }
}
