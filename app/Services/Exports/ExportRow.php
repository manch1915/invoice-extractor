<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Domain\Money\Minor;
use App\Models\Document;

/**
 * One document flattened into the columns every spreadsheet-like target shares.
 */
final readonly class ExportRow
{
    public function __construct(private Document $document) {}

    public function issueDate(string $format = 'Y-m-d'): string
    {
        return $this->document->issue_date?->format($format) ?? '';
    }

    public function dueDate(string $format = 'Y-m-d'): string
    {
        return $this->document->due_date?->format($format) ?? '';
    }

    public function number(): string
    {
        return (string) $this->document->document_number;
    }

    public function vendor(): string
    {
        return (string) $this->document->vendor_name;
    }

    public function taxId(): string
    {
        return (string) $this->document->vendor_tax_id;
    }

    public function category(string $locale): string
    {
        $category = $this->document->category;

        return $category ? $category->name($locale) : '';
    }

    public function amount(?int $minor): ?float
    {
        return $minor === null ? null : (float) Minor::toDecimal($minor);
    }

    public function decimal(?int $minor, string $separator = '.'): string
    {
        return $minor === null ? '' : str_replace('.', $separator, Minor::toDecimal($minor));
    }

    public function document(): Document
    {
        return $this->document;
    }
}
