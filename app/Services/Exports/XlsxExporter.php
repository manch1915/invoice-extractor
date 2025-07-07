<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Models\Export;
use App\Models\LineItem;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** A workbook with one sheet of documents and one of their lines, ready for filters and pivots. */
class XlsxExporter implements Exporter
{
    private const MONEY = '#,##0.00';

    public function __construct(private readonly Filesystem $disk) {}

    public function export(Export $export, Collection $documents): ExportResult
    {
        $locale = app()->getLocale();
        $book = new Spreadsheet;
        $book->getProperties()->setTitle('Invoice Extractor export')->setCreator('Invoice Extractor');

        $sheet = $book->getActiveSheet()->setTitle($locale === 'ru' ? 'Документы' : 'Documents');
        $headers = $locale === 'ru'
            ? ['Дата', 'Номер', 'Поставщик', 'ИНН / VAT', 'Категория', 'Счёт Xero', 'Счёт 1С', 'Без налога', 'Ставка, %', 'Налог', 'Итого', 'Валюта', 'Срок оплаты', 'Файл']
            : ['Date', 'Number', 'Supplier', 'Tax ID', 'Category', 'Xero account', '1C account', 'Net', 'Tax rate, %', 'Tax', 'Total', 'Currency', 'Due', 'File'];
        $sheet->fromArray($headers);

        $row = 2;
        foreach ($documents as $document) {
            $r = new ExportRow($document);
            $this->writeRow($sheet, $row, [
                $r->issueDate(), $r->number(), $r->vendor(), $r->taxId(), $r->category($locale),
                (string) $document->category?->account_code, (string) $document->category?->account_1c,
                $r->amount($document->subtotal), $document->tax_rate !== null ? (float) $document->tax_rate : null,
                $r->amount($document->tax_amount), $r->amount($document->total), (string) $document->currency,
                $r->dueDate(), $document->original_filename,
            ]);
            $row++;
        }
        $this->format($sheet, $row - 1, ['H', 'J', 'K']);

        $lines = $book->createSheet()->setTitle($locale === 'ru' ? 'Позиции' : 'Lines');
        $lines->fromArray($locale === 'ru'
            ? ['Номер документа', 'Поставщик', '№', 'Наименование', 'Количество', 'Цена', 'Сумма', 'Валюта']
            : ['Document number', 'Supplier', '#', 'Description', 'Quantity', 'Price', 'Amount', 'Currency']);
        $row = 2;
        foreach ($documents as $document) {
            $r = new ExportRow($document);
            /** @var LineItem $item */
            foreach ($document->lineItems as $item) {
                $this->writeRow($lines, $row, [
                    $r->number(), $r->vendor(), $item->position, $item->description,
                    $item->quantity !== null ? (float) $item->quantity : null,
                    $r->amount($item->unit_price), $r->amount($item->amount), (string) $document->currency,
                ]);
                $row++;
            }
        }
        $this->format($lines, $row - 1, ['F', 'G']);
        $book->setActiveSheetIndex(0);

        $path = "exports/{$export->id}.xlsx";
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($book))->save($tmp);
        $this->disk->put($path, (string) file_get_contents($tmp));
        @unlink($tmp);

        return new ExportResult(filePath: $path);
    }

    /**
     * Text from documents is untrusted: a supplier named "=HYPERLINK(...)" must stay text, so strings
     * are always written as strings and never parsed as formulas, and identifiers like 10.01 stay as typed.
     *
     * @param  list<string|int|float|null>  $values
     */
    private function writeRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $index => $value) {
            $cell = Coordinate::stringFromColumnIndex($index + 1).$row;
            if ($value === null || $value === '') {
                continue;
            }
            is_string($value)
                ? $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING)
                : $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_NUMERIC);
        }
    }

    /** @param  list<string>  $moneyColumns */
    private function format(Worksheet $sheet, int $lastRow, array $moneyColumns): void
    {
        $lastColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}".max(1, $lastRow));
        foreach ($moneyColumns as $column) {
            $sheet->getStyle("{$column}2:{$column}".max(2, $lastRow))->getNumberFormat()->setFormatCode(self::MONEY);
        }
        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }
    }
}
