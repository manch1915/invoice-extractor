<?php

declare(strict_types=1);

namespace App\Domain\Validation\Checks;

use App\Domain\Validation\Check;
use App\Domain\Validation\DocumentFacts;
use App\Domain\Validation\Issue;
use App\Enums\IssueCode;

/** Without a category the exports have no ledger account to post to. */
final class CategoryCheck implements Check
{
    public function run(DocumentFacts $facts): array
    {
        return $facts->hasCategory ? [] : [Issue::blocking(IssueCode::NoCategory)];
    }
}
