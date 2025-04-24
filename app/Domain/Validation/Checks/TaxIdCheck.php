<?php

declare(strict_types=1);

namespace App\Domain\Validation\Checks;

use App\Domain\TaxId\TaxIdValidator;
use App\Domain\Validation\Check;
use App\Domain\Validation\DocumentFacts;
use App\Domain\Validation\Issue;
use App\Enums\FieldKey;
use App\Enums\IssueCode;

final class TaxIdCheck implements Check
{
    public function __construct(private readonly TaxIdValidator $validator) {}

    public function run(DocumentFacts $facts): array
    {
        if ($facts->vendorTaxId === null || $facts->vendorTaxId === '') {
            return [];
        }

        $check = $this->validator->check($facts->vendorTaxId);
        if ($check->valid) {
            return [];
        }

        return [Issue::blocking(IssueCode::InvalidTaxId, FieldKey::VendorTaxId, [
            'kind' => $check->kind->value,
            'value' => $facts->vendorTaxId,
        ])];
    }
}
