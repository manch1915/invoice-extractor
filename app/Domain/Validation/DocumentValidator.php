<?php

declare(strict_types=1);

namespace App\Domain\Validation;

use App\Domain\TaxId\TaxIdValidator;
use App\Domain\Validation\Checks\AmountAnomalyCheck;
use App\Domain\Validation\Checks\CategoryCheck;
use App\Domain\Validation\Checks\DateCheck;
use App\Domain\Validation\Checks\DuplicateCheck;
use App\Domain\Validation\Checks\LineItemsCheck;
use App\Domain\Validation\Checks\RequiredFieldsCheck;
use App\Domain\Validation\Checks\TaxCheck;
use App\Domain\Validation\Checks\TaxIdCheck;
use App\Domain\Validation\Checks\TotalsCheck;

final class DocumentValidator
{
    /** @var list<Check> */
    private array $checks;

    /** @param  list<Check>|null  $checks */
    public function __construct(?array $checks = null)
    {
        $this->checks = $checks ?? [
            new RequiredFieldsCheck,
            new TotalsCheck,
            new LineItemsCheck,
            new TaxCheck,
            new TaxIdCheck(new TaxIdValidator),
            new DateCheck,
            new DuplicateCheck,
            new AmountAnomalyCheck,
            new CategoryCheck,
        ];
    }

    /** @return list<Issue> */
    public function validate(DocumentFacts $facts): array
    {
        $issues = [];
        foreach ($this->checks as $check) {
            array_push($issues, ...$check->run($facts));
        }

        return $issues;
    }
}
