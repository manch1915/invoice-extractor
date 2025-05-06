<?php

declare(strict_types=1);

use App\Enums\FieldKey;
use App\Services\FieldNormalizer;
use Illuminate\Validation\ValidationException;

it('normalizes what people type into stored values', function (FieldKey $key, string $input, ?string $expected) {
    expect((new FieldNormalizer)->normalize($key, $input))->toBe($expected);
})->with([
    'ru money' => [FieldKey::Total, '1 234,56', '1234.56'],
    'en money' => [FieldKey::Total, '1,234.56', '1234.56'],
    'eu money' => [FieldKey::Subtotal, '4.890,00 €', '4890.00'],
    'whole money' => [FieldKey::TaxAmount, '1585', '1585.00'],
    'ru date' => [FieldKey::IssueDate, '05.10.2026', '2026-10-05'],
    'iso date' => [FieldKey::DueDate, '2026-10-05', '2026-10-05'],
    'rate' => [FieldKey::TaxRate, '20 %', '20'],
    'fraction rate' => [FieldKey::TaxRate, '8,875', '8.88'],
    'currency' => [FieldKey::Currency, 'rub', 'RUB'],
    'tax id' => [FieldKey::VendorTaxId, 'GB 284 6175 39', 'GB284617539'],
    'empty' => [FieldKey::VendorName, '   ', null],
]);

it('refuses values that cannot be what the field holds', function (FieldKey $key, string $input) {
    (new FieldNormalizer)->normalize($key, $input);
})->with([
    [FieldKey::Total, 'twelve'],
    [FieldKey::IssueDate, '31.02.2026'],
    [FieldKey::Currency, 'rubles'],
    [FieldKey::TaxRate, '120'],
])->throws(ValidationException::class);
