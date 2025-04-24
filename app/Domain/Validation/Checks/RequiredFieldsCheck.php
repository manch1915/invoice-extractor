<?php

declare(strict_types=1);

namespace App\Domain\Validation\Checks;

use App\Domain\Validation\Check;
use App\Domain\Validation\DocumentFacts;
use App\Domain\Validation\Issue;
use App\Enums\FieldKey;
use App\Enums\IssueCode;

final class RequiredFieldsCheck implements Check
{
    public function run(DocumentFacts $facts): array
    {
        $issues = [];
        $required = [
            [FieldKey::VendorName, $facts->vendorName],
            [FieldKey::IssueDate, $facts->issueDate],
            [FieldKey::Currency, $facts->currency],
            [FieldKey::Total, $facts->total],
        ];
        foreach ($required as [$field, $value]) {
            if ($value === null || $value === '') {
                $issues[] = Issue::blocking(IssueCode::MissingField, $field);
            }
        }

        // Shop receipts often carry no number worth booking, so ask without blocking.
        if ($facts->documentNumber === null || $facts->documentNumber === '') {
            $issues[] = Issue::warning(IssueCode::MissingField, FieldKey::DocumentNumber);
        }

        return $issues;
    }
}
