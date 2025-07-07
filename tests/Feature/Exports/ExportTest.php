<?php

declare(strict_types=1);

use App\Enums\DocumentSource;
use App\Enums\DocumentStatus;
use App\Enums\ExportStatus;
use App\Enums\ExportTarget;
use App\Models\Document;
use App\Models\Export;
use App\Models\Integration;
use App\Services\Documents\DocumentIngestor;
use App\Services\Exports\ExportService;
use App\Services\Exports\XeroExporter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(fn () => seedBasics());

function readyDocument(string $fixture = 'kanc-0929'): Document
{
    fakeWorker(workerFixture($fixture));
    $document = app(DocumentIngestor::class)->ingest(uniqid(), "{$fixture}.pdf", 'application/pdf', DocumentSource::Upload, accountant())->refresh();
    expect($document->status)->toBe(DocumentStatus::Ready);

    return $document;
}

function runExport(ExportTarget $target, Document ...$documents): Export
{
    return app(ExportService::class)->start($target, array_map(fn (Document $d) => $d->id, $documents), accountant())->refresh();
}

it('writes an Excel workbook with documents and their lines', function () {
    $document = readyDocument();

    $export = runExport(ExportTarget::Xlsx, $document);

    expect($export->status)->toBe(ExportStatus::Done)
        ->and($document->refresh()->status)->toBe(DocumentStatus::Exported);

    $path = Storage::disk('local')->path($export->file_path);
    $book = IOFactory::load($path);
    $sheet = $book->getSheet(0);
    expect($sheet->getCell('B2')->getValue())->toBe('КМ-21688')
        ->and($sheet->getCell('K2')->getValue())->toEqual(6002.0)
        ->and($sheet->getCell('F2')->getValue())->toBe('461')
        ->and($book->getSheet(1)->getCell('D2')->getValue())->toStartWith('Бумага офисная');
});

it('writes a CSV that 1C reads: cp1251, semicolons, decimal commas', function () {
    $document = readyDocument();

    $export = runExport(ExportTarget::OneC, $document);

    $raw = Storage::disk('local')->get($export->file_path);
    expect(mb_check_encoding($raw, 'UTF-8'))->toBeFalse();
    $lines = explode("\r\n", trim(mb_convert_encoding($raw, 'UTF-8', 'Windows-1251')));
    expect($lines[0])->toStartWith('Дата;Номер;Вид документа;Контрагент')
        ->and($lines[1])->toBe('29.09.2026;КМ-21688;Счет на оплату;ООО «КанцМаркет»;7719053389;6002,00;20%;1000,33;руб.;Канцелярия и офис;26;06.10.2026;kanc-0929.pdf');
});

it('neutralizes values that a spreadsheet would run as formulas', function () {
    $document = readyDocument();
    $document->update(['vendor_name' => '=HYPERLINK("http://evil.test")']);

    $export = runExport(ExportTarget::OneC, $document);

    $lines = explode("\r\n", mb_convert_encoding(Storage::disk('local')->get($export->file_path), 'UTF-8', 'Windows-1251'));
    expect($lines[1])->toContain(";\"'=HYPERLINK(");
});

it('keeps formula-looking text as plain text in Excel', function () {
    $document = readyDocument();
    $document->update(['vendor_name' => '=HYPERLINK("http://evil.test","open")']);

    $export = runExport(ExportTarget::Xlsx, $document);

    $cell = IOFactory::load(Storage::disk('local')->path($export->file_path))->getSheet(0)->getCell('C2');
    expect($cell->getDataType())->toBe('s')
        ->and($cell->getValue())->toBe('=HYPERLINK("http://evil.test","open")');
});

it('only exports approved documents', function () {
    $payload = workerFixture('kanc-0929');
    $payload['fields']['total']['confidence'] = 0.3;
    fakeWorker($payload);
    $document = app(DocumentIngestor::class)->ingest('x', 'x.pdf', 'application/pdf', DocumentSource::Upload, accountant());

    expect(fn () => app(ExportService::class)->start(ExportTarget::Xlsx, [$document->id], accountant()))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('turns a Russian invoice into a draft Xero bill with VAT-inclusive lines', function () {
    $document = readyDocument()->load(['category', 'lineItems']);

    $bill = app(XeroExporter::class)->bill($document);

    expect($bill)->toMatchArray([
        'Type' => 'ACCPAY',
        'Status' => 'DRAFT',
        'Contact' => ['Name' => 'ООО «КанцМаркет»'],
        'Date' => '2026-09-29',
        'InvoiceNumber' => 'КМ-21688',
        'CurrencyCode' => 'RUB',
        'LineAmountTypes' => 'Inclusive',
    ])->and($bill['LineItems'][0])->toBe([
        'Description' => 'Бумага офисная А4, 500 л.',
        'Quantity' => 8.0,
        'UnitAmount' => 399.0,
        'AccountCode' => '461',
    ]);
});

it('sends bills to Xero, refreshing the token first, and keeps their ids', function () {
    $document = readyDocument();
    Integration::create([
        'provider' => Integration::XERO,
        'credentials' => ['access_token' => 'old', 'refresh_token' => 'refresh-1'],
        'expires_at' => now()->subMinute(),
        'settings' => ['tenant_id' => 'tenant-42'],
    ]);
    config(['invoices.xero.client_id' => 'id', 'invoices.xero.client_secret' => 'secret']);
    Http::fake([
        'identity.xero.com/connect/token' => Http::response(['access_token' => 'fresh', 'refresh_token' => 'refresh-2', 'expires_in' => 1800]),
        'api.xero.com/api.xro/2.0/Invoices*' => Http::response(['Invoices' => [['InvoiceID' => 'bill-uuid-1']]]),
    ]);

    $export = runExport(ExportTarget::Xero, $document);

    expect($export->status)->toBe(ExportStatus::Done)
        ->and($export->documents()->first()->pivot->external_id)->toBe('bill-uuid-1')
        ->and(Integration::first()->credentials['refresh_token'])->toBe('refresh-2');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/Invoices') && $r->header('xero-tenant-id') === ['tenant-42'] && $r->header('Authorization') === ['Bearer fresh']);
});

it('records why an export failed', function () {
    $document = readyDocument();

    $export = runExport(ExportTarget::Xero, $document);

    expect($export->status)->toBe(ExportStatus::Failed)
        ->and($export->error)->toBe('Xero is not connected')
        ->and($document->refresh()->status)->toBe(DocumentStatus::Ready);
});

it('creates the Google spreadsheet once and appends to it after', function () {
    $first = readyDocument();
    Integration::create([
        'provider' => Integration::GOOGLE,
        'credentials' => ['access_token' => 'token', 'refresh_token' => 'r'],
        'expires_at' => now()->addHour(),
    ]);
    Http::fake([
        'sheets.googleapis.com/v4/spreadsheets' => Http::response(['spreadsheetId' => 'sheet-1', 'spreadsheetUrl' => 'https://docs.google.com/spreadsheets/d/sheet-1']),
        'sheets.googleapis.com/v4/spreadsheets/sheet-1/values/*' => Http::response(['updates' => []]),
    ]);

    $export = runExport(ExportTarget::GoogleSheets, $first);

    expect($export->external_url)->toBe('https://docs.google.com/spreadsheets/d/sheet-1')
        ->and(Integration::first()->setting('spreadsheet')['id'])->toBe('sheet-1');
    Http::assertSentCount(3);
});
