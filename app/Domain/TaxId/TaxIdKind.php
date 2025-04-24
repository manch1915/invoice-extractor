<?php

declare(strict_types=1);

namespace App\Domain\TaxId;

enum TaxIdKind: string
{
    case Inn = 'inn';
    case Vat = 'vat';
    case Ein = 'ein';
    case Unknown = 'unknown';
}
