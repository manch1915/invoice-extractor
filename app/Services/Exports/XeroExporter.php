<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Domain\Money\Minor;
use App\Infrastructure\Integrations\Xero;
use App\Models\Document;
use App\Models\Export;
use App\Models\Integration;
use App\Models\LineItem;
use Illuminate\Support\Collection;
use RuntimeException;

/** Sends documents to Xero as draft bills, so the bookkeeper approves them there. */
class XeroExporter implements Exporter
{
    private const BATCH = 50;

    private const DEFAULT_ACCOUNT = '429';

    public function __construct(private readonly Xero $xero) {}

    public function export(Export $export, Collection $documents): ExportResult
    {
        $integration = Integration::query()->where('provider', Integration::XERO)->first();
        $tenant = $integration?->setting('tenant_id');
        if ($integration === null || ! is_string($tenant)) {
            throw new RuntimeException('Xero is not connected');
        }
        $token = $this->xero->accessToken($integration);

        $externalIds = [];
        foreach ($documents->values()->chunk(self::BATCH) as $batch) {
            $ids = $this->xero->createBills($token, $tenant, array_values($batch->map(fn (Document $d) => $this->bill($d))->all()));
            foreach ($batch->values() as $i => $document) {
                if (isset($ids[$i])) {
                    $externalIds[$document->id] = (string) $ids[$i];
                }
            }
        }

        return new ExportResult(externalUrl: 'https://go.xero.com/AccountsPayable/Search.aspx', externalIds: $externalIds);
    }

    /** @return array<string, mixed> */
    public function bill(Document $document): array
    {
        $account = $document->category->account_code ?? self::DEFAULT_ACCOUNT;
        $items = $document->lineItems->filter(fn (LineItem $i) => $i->amount !== null);

        $bill = [
            'Type' => 'ACCPAY',
            'Status' => 'DRAFT',
            'Contact' => ['Name' => (string) $document->vendor_name],
            'Date' => $document->issue_date?->toDateString(),
            'DueDate' => ($document->due_date ?? $document->issue_date?->addDays(14))?->toDateString(),
            'InvoiceNumber' => (string) $document->document_number,
            'Reference' => (string) $document->document_number,
            'CurrencyCode' => $document->currency,
            'LineAmountTypes' => $this->amountTypes($document, $items),
        ];

        $bill['LineItems'] = $items->isNotEmpty()
            ? $items->map(fn (LineItem $i) => [
                'Description' => $i->description,
                'Quantity' => $i->quantity !== null ? (float) $i->quantity : 1,
                'UnitAmount' => (float) Minor::toDecimal((int) ($i->unit_price ?? $i->amount)),
                'AccountCode' => $account,
            ])->values()->all()
            : [[
                'Description' => trim(($document->category->name_en ?? 'Expense').' '.$document->document_number),
                'Quantity' => 1,
                'UnitAmount' => (float) Minor::toDecimal((int) ($document->subtotal ?? $document->total)),
                'AccountCode' => $account,
            ]];

        return $bill;
    }

    /** @param  Collection<int, LineItem>  $items */
    private function amountTypes(Document $document, Collection $items): string
    {
        if ((int) $document->tax_amount === 0) {
            return 'NoTax';
        }
        if ($items->isEmpty()) {
            return 'Exclusive';
        }

        // Russian invoices print prices with VAT inside; English ones add tax on top
        return abs((int) $items->sum('amount') - (int) $document->total) <= $items->count() ? 'Inclusive' : 'Exclusive';
    }
}
