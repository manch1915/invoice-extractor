<?php

declare(strict_types=1);

namespace App\Domain\Validation;

interface Check
{
    /** @return list<Issue> */
    public function run(DocumentFacts $facts): array;
}
