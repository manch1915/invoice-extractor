<?php

declare(strict_types=1);

namespace App\Domain\TaxId;

final class TaxId
{
    /** One canonical form everywhere it is stored or compared: "GB 284-6175.39" becomes "GB284617539". */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $canonical = strtoupper((string) preg_replace('/[\s\-.]/u', '', $value));

        return $canonical === '' ? null : $canonical;
    }
}
