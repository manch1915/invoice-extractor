<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Money\Minor;
use App\Domain\TaxId\TaxId;
use App\Enums\FieldKey;
use DateTimeImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Turns what a person typed into the stored format: "1 234,56" becomes "1234.56", "05.10.2026" becomes "2026-10-05".
 */
class FieldNormalizer
{
    public function normalize(FieldKey $key, ?string $input): ?string
    {
        $value = $input === null ? '' : trim($input);
        if ($value === '') {
            return null;
        }

        return match (true) {
            $key->isMoney() => $this->money($key, $value),
            $key->isDate() => $this->date($key, $value),
            $key === FieldKey::TaxRate => $this->rate($value),
            $key === FieldKey::Currency => $this->currency($value),
            $key === FieldKey::VendorTaxId, $key === FieldKey::BuyerTaxId => TaxId::normalize($value),
            default => mb_substr($value, 0, 255),
        };
    }

    private function money(FieldKey $key, string $value): string
    {
        $clean = (string) preg_replace('/[^\d.,\-]/', '', $value);
        $lastDot = strrpos($clean, '.');
        $lastComma = strrpos($clean, ',');
        $decimalSep = match (true) {
            $lastDot !== false && $lastComma !== false => $lastDot > $lastComma ? '.' : ',',
            $lastComma !== false && strlen($clean) - $lastComma - 1 <= 2 => ',',
            $lastDot !== false && strlen($clean) - $lastDot - 1 <= 2 => '.',
            default => null,
        };
        if ($decimalSep !== null) {
            [$whole, $fraction] = [substr($clean, 0, (int) strrpos($clean, $decimalSep)), substr($clean, (int) strrpos($clean, $decimalSep) + 1)];
            $clean = str_replace(['.', ','], '', $whole).'.'.$fraction;
        } else {
            $clean = str_replace(['.', ','], '', $clean);
        }

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $clean)) {
            throw ValidationException::withMessages(['value' => __('validation.money', ['field' => $key->value])]);
        }

        return Minor::toDecimal(Minor::fromDecimal($clean));
    }

    private function date(FieldKey $key, string $value): string
    {
        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'd.m.y', 'Y/m/d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        throw ValidationException::withMessages(['value' => __('validation.document_date', ['field' => $key->value])]);
    }

    private function rate(string $value): string
    {
        $clean = str_replace([',', '%', ' '], ['.', '', ''], $value);
        if (! is_numeric($clean) || (float) $clean < 0 || (float) $clean > 100) {
            throw ValidationException::withMessages(['value' => __('validation.tax_rate')]);
        }

        return rtrim(rtrim(number_format((float) $clean, 2, '.', ''), '0'), '.');
    }

    private function currency(string $value): string
    {
        $code = strtoupper($value);
        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            throw ValidationException::withMessages(['value' => __('validation.currency')]);
        }

        return $code;
    }
}
