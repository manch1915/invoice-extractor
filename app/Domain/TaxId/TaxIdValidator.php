<?php

declare(strict_types=1);

namespace App\Domain\TaxId;

final class TaxIdValidator
{
    /** EU and UK VAT numbers: country prefix plus the national format. */
    private const VAT_FORMATS = [
        'GB' => '/^GB(\d{9}|\d{12}|GD\d{3}|HA\d{3})$/',
        'DE' => '/^DE\d{9}$/',
        'FR' => '/^FR[0-9A-Z]{2}\d{9}$/',
        'NL' => '/^NL\d{9}B\d{2}$/',
        'IT' => '/^IT\d{11}$/',
        'ES' => '/^ES[0-9A-Z]\d{7}[0-9A-Z]$/',
        'PL' => '/^PL\d{10}$/',
        'AT' => '/^ATU\d{8}$/',
    ];

    public function check(string $value): TaxIdCheck
    {
        $id = strtoupper((string) preg_replace('/[\s\-.]/', '', $value));

        if (preg_match('/^\d{10}$|^\d{12}$/', $id)) {
            return new TaxIdCheck(TaxIdKind::Inn, $this->innChecksumOk($id));
        }

        $prefix = substr($id, 0, 2);
        if (isset(self::VAT_FORMATS[$prefix])) {
            return new TaxIdCheck(TaxIdKind::Vat, (bool) preg_match(self::VAT_FORMATS[$prefix], $id));
        }

        if (preg_match('/^\d{9}$/', $id)) {
            return new TaxIdCheck(TaxIdKind::Ein, true);
        }

        return new TaxIdCheck(TaxIdKind::Unknown, true);
    }

    private function innChecksumOk(string $inn): bool
    {
        $digits = array_map('intval', str_split($inn));

        if (count($digits) === 10) {
            return $this->checkDigit($digits, [2, 4, 10, 3, 5, 9, 4, 6, 8]) === $digits[9];
        }

        return $this->checkDigit($digits, [7, 2, 4, 10, 3, 5, 9, 4, 6, 8]) === $digits[10]
            && $this->checkDigit($digits, [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8]) === $digits[11];
    }

    /**
     * @param  list<int>  $digits
     * @param  list<int>  $weights
     */
    private function checkDigit(array $digits, array $weights): int
    {
        $sum = 0;
        foreach ($weights as $i => $weight) {
            $sum += $digits[$i] * $weight;
        }

        return $sum % 11 % 10;
    }
}
