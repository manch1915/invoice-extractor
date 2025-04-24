<?php

declare(strict_types=1);

namespace App\Domain\Validation\Checks;

use App\Domain\Validation\Check;
use App\Domain\Validation\DocumentFacts;
use App\Domain\Validation\Issue;
use App\Enums\FieldKey;
use App\Enums\IssueCode;

/** Lines must add up to the total (prices with tax) or to the net amount (prices without). */
final class LineItemsCheck implements Check
{
    public function run(DocumentFacts $facts): array
    {
        $amounts = $facts->itemAmounts;
        if ($amounts === [] || in_array(null, $amounts, true) || $facts->total === null) {
            return [];
        }

        $sum = array_sum($amounts);
        // a kopeck of rounding per line is normal on Russian invoices
        $tolerance = max(2, count($amounts));
        foreach ([$facts->total, $facts->subtotal] as $target) {
            if ($target !== null && abs($sum - $target) <= $tolerance) {
                return [];
            }
        }

        return [Issue::blocking(IssueCode::ItemsMismatch, FieldKey::Total, [
            'items_sum' => $sum,
            'total' => $facts->total,
        ])];
    }
}
