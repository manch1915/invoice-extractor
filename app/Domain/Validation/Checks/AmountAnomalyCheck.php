<?php

declare(strict_types=1);

namespace App\Domain\Validation\Checks;

use App\Domain\Validation\Check;
use App\Domain\Validation\DocumentFacts;
use App\Domain\Validation\Issue;
use App\Enums\FieldKey;
use App\Enums\IssueCode;

/** Flags a bill far above what this vendor usually charges. */
final class AmountAnomalyCheck implements Check
{
    private const FACTOR = 3;

    private const MIN_HISTORY = 2;

    public function run(DocumentFacts $facts): array
    {
        $history = $facts->vendorHistory;
        if ($facts->total === null || count($history) < self::MIN_HISTORY) {
            return [];
        }

        sort($history);
        $middle = intdiv(count($history), 2);
        $median = count($history) % 2 === 1
            ? $history[$middle]
            : intdiv($history[$middle - 1] + $history[$middle], 2);

        if ($median <= 0 || $facts->total <= $median * self::FACTOR) {
            return [];
        }

        return [Issue::warning(IssueCode::UnusualAmount, FieldKey::Total, [
            'median' => $median,
            'ratio' => round($facts->total / $median, 1),
        ])];
    }
}
