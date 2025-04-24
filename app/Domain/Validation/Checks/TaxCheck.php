<?php

declare(strict_types=1);

namespace App\Domain\Validation\Checks;

use App\Domain\Validation\Check;
use App\Domain\Validation\DocumentFacts;
use App\Domain\Validation\Issue;
use App\Enums\FieldKey;
use App\Enums\IssueCode;

/** The printed tax should be the stated rate of the net amount. */
final class TaxCheck implements Check
{
    public function run(DocumentFacts $facts): array
    {
        if ($facts->taxRate === null || $facts->taxAmount === null || $facts->subtotal === null) {
            return [];
        }

        $expected = (int) round($facts->subtotal * (float) $facts->taxRate / 100);
        $tolerance = max(5, (int) round(abs($expected) * 0.005));
        if (abs($expected - $facts->taxAmount) <= $tolerance) {
            return [];
        }

        return [Issue::blocking(IssueCode::TaxMismatch, FieldKey::TaxAmount, [
            'rate' => $facts->taxRate,
            'expected' => $expected,
            'actual' => $facts->taxAmount,
        ])];
    }
}
