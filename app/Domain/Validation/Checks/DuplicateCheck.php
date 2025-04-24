<?php

declare(strict_types=1);

namespace App\Domain\Validation\Checks;

use App\Domain\Validation\Check;
use App\Domain\Validation\DocumentFacts;
use App\Domain\Validation\Issue;
use App\Enums\FieldKey;
use App\Enums\IssueCode;

/**
 * A suspected duplicate is never dropped silently: it waits for a person who can see both copies.
 */
final class DuplicateCheck implements Check
{
    public function run(DocumentFacts $facts): array
    {
        if ($facts->duplicateIds === []) {
            return [];
        }

        return [Issue::blocking(IssueCode::Duplicate, FieldKey::DocumentNumber, [
            'original_id' => $facts->duplicateIds[0],
            'count' => count($facts->duplicateIds),
        ])];
    }
}
