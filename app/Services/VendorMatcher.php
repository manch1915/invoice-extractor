<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\TaxId\TaxId;
use App\Models\Vendor;

/**
 * Links a document to a vendor record: tax id first, then the cleaned-up name.
 * It never edits an existing vendor, so a document nobody has checked cannot rewrite who a vendor is.
 */
class VendorMatcher
{
    public function match(?string $name, ?string $taxId): ?Vendor
    {
        $name = $name !== null ? trim($name) : '';
        $taxId = TaxId::normalize($taxId);
        if ($name === '' && $taxId === null) {
            return null;
        }

        if ($taxId !== null) {
            $vendor = Vendor::query()->where('tax_id', $taxId)->first();
            if ($vendor) {
                return $vendor;
            }
        }

        $normalized = $name !== '' ? Vendor::normalize($name) : '';
        if ($normalized !== '') {
            $byName = Vendor::query()->where('normalized_name', $normalized)->get();
            // same name with a different tax id is a different company (or a lookalike), not a match
            $vendor = $byName->first(fn (Vendor $v) => $v->tax_id === null || $taxId === null || $v->tax_id === $taxId);
            if ($vendor) {
                return $vendor;
            }
        }

        if ($name === '') {
            return null;
        }

        return Vendor::create([
            'name' => $name,
            'normalized_name' => $normalized,
            'tax_id' => $taxId,
            'country' => $this->guessCountry($taxId),
        ]);
    }

    private function guessCountry(?string $taxId): ?string
    {
        if ($taxId === null) {
            return null;
        }
        if (preg_match('/^\d{10}$|^\d{12}$/', $taxId)) {
            return 'RU';
        }
        if (preg_match('/^([A-Z]{2})/', $taxId, $m)) {
            return $m[1];
        }

        return preg_match('/^\d{9}$/', $taxId) ? 'US' : null;
    }
}
