<?php

declare(strict_types=1);

namespace App\Domain\Validation\Checks;

use App\Domain\Validation\Check;
use App\Domain\Validation\DocumentFacts;
use App\Domain\Validation\Issue;
use App\Enums\FieldKey;
use App\Enums\IssueCode;

final class DateCheck implements Check
{
    public function run(DocumentFacts $facts): array
    {
        $issues = [];
        $issued = $facts->issueDate;

        if ($issued !== null && $issued > $facts->today->modify('+1 day')) {
            $issues[] = Issue::blocking(IssueCode::FutureDate, FieldKey::IssueDate, [
                'date' => $issued->format('Y-m-d'),
            ]);
        } elseif ($issued !== null && $issued < $facts->today->modify('-18 months')) {
            $issues[] = Issue::warning(IssueCode::StaleDate, FieldKey::IssueDate, [
                'date' => $issued->format('Y-m-d'),
            ]);
        }

        if ($issued !== null && $facts->dueDate !== null && $facts->dueDate < $issued) {
            $issues[] = Issue::warning(IssueCode::DueBeforeIssue, FieldKey::DueDate, [
                'due' => $facts->dueDate->format('Y-m-d'),
                'issued' => $issued->format('Y-m-d'),
            ]);
        }

        return $issues;
    }
}
