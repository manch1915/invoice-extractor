<?php

declare(strict_types=1);

namespace App\Domain\TaxId;

final readonly class TaxIdCheck
{
    public function __construct(
        public TaxIdKind $kind,
        public bool $valid,
    ) {}
}
