<?php

declare(strict_types=1);

namespace App\Domain\Validation\Checks;

use App\Domain\Validation\Check;
use App\Domain\Validation\DocumentFacts;
use App\Domain\Validation\Issue;
use App\Enums\FieldKey;
use App\Enums\IssueCode;

/** Net plus tax must equal the total. */
final class TotalsCheck implements Check
{
    private const TOLERANCE = 2;

    public function run(DocumentFacts $facts): array
    {
        if ($facts->subtotal === null || $facts->taxAmount === null || $facts->total === null) {
            return [];
        }

        $expected = $facts->subtotal + $facts->taxAmount;
        if (abs($expected - $facts->total) <= self::TOLERANCE) {
            return [];
        }

        return [Issue::blocking(IssueCode::TotalsMismatch, FieldKey::Total, [
            'expected' => $expected,
            'actual' => $facts->total,
        ])];
    }
}
