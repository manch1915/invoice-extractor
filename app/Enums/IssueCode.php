<?php

declare(strict_types=1);

namespace App\Enums;

enum IssueCode: string
{
    case MissingField = 'missing_field';
    case TotalsMismatch = 'totals_mismatch';
    case ItemsMismatch = 'items_mismatch';
    case TaxMismatch = 'tax_mismatch';
    case InvalidTaxId = 'invalid_tax_id';
    case FutureDate = 'future_date';
    case DueBeforeIssue = 'due_before_issue';
    case StaleDate = 'stale_date';
    case Duplicate = 'duplicate';
    case UnusualAmount = 'unusual_amount';
    case NoCategory = 'no_category';
}
