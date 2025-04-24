<?php

declare(strict_types=1);

use App\Domain\TaxId\TaxIdKind;
use App\Domain\TaxId\TaxIdValidator;

it('checks Russian INN check digits', function (string $inn, bool $valid) {
    $check = (new TaxIdValidator)->check($inn);

    expect($check->kind)->toBe(TaxIdKind::Inn)
        ->and($check->valid)->toBe($valid);
})->with([
    'company, valid' => ['7728491068', true],
    'company, wrong last digit' => ['7728510344', false],
    'sole trader, valid' => ['500312774525', true],
    'sole trader, broken' => ['500312774526', false],
]);

it('checks EU and UK VAT formats', function (string $vat, bool $valid) {
    $check = (new TaxIdValidator)->check($vat);

    expect($check->kind)->toBe(TaxIdKind::Vat)
        ->and($check->valid)->toBe($valid);
})->with([
    ['GB 284 6175 39', true],
    ['DE318452907', true],
    ['DE31845290', false],
    ['GB12345', false],
]);

it('accepts identifiers it does not know how to verify', function () {
    expect((new TaxIdValidator)->check('842219573')->kind)->toBe(TaxIdKind::Ein)
        ->and((new TaxIdValidator)->check('X-77')->valid)->toBeTrue();
});
