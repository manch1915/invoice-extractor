<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vendor;

/**
 * Links a document to a vendor record: tax id first, then the cleaned-up name.
 */
class VendorMatcher
{
    public function match(?string $name, ?string $taxId): ?Vendor
    {
        $name = $name !== null ? trim($name) : null;
        $taxId = $taxId !== null ? preg_replace('/[\s\-]/', '', $taxId) : null;
        if (($name === null || $name === '') && ($taxId === null || $taxId === '')) {
            return null;
        }

        if ($taxId) {
            $vendor = Vendor::query()->where('tax_id', $taxId)->first();
            if ($vendor) {
                return $vendor;
            }
        }

        $normalized = $name ? Vendor::normalize($name) : '';
        if ($normalized !== '') {
            $vendor = Vendor::query()->where('normalized_name', $normalized)->first();
            if ($vendor) {
                if ($taxId && ! $vendor->tax_id) {
                    $vendor->update(['tax_id' => $taxId]);
                }

                return $vendor;
            }
        }

        if (! $name) {
            return null;
        }

        return Vendor::create([
            'name' => $name,
            'normalized_name' => $normalized,
            'tax_id' => $taxId ?: null,
            'country' => $this->guessCountry($taxId),
        ]);
    }

    private function guessCountry(?string $taxId): ?string
    {
        if (! $taxId) {
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
