<?php

declare(strict_types=1);

namespace App\Enums;

enum FieldKey: string
{
    case VendorName = 'vendor_name';
    case VendorTaxId = 'vendor_tax_id';
    case BuyerName = 'buyer_name';
    case BuyerTaxId = 'buyer_tax_id';
    case DocumentNumber = 'document_number';
    case IssueDate = 'issue_date';
    case DueDate = 'due_date';
    case Currency = 'currency';
    case Subtotal = 'subtotal';
    case TaxRate = 'tax_rate';
    case TaxAmount = 'tax_amount';
    case Total = 'total';

    public function isMoney(): bool
    {
        return in_array($this, [self::Subtotal, self::TaxAmount, self::Total], true);
    }

    public function isDate(): bool
    {
        return in_array($this, [self::IssueDate, self::DueDate], true);
    }

    /** Fields that must be right before a document can go to the books without a human look. */
    public function isKey(): bool
    {
        return in_array($this, [self::VendorName, self::DocumentNumber, self::IssueDate, self::Currency, self::Total, self::TaxAmount], true);
    }
}
