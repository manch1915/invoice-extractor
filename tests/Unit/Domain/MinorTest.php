<?php

declare(strict_types=1);

use App\Domain\Money\Minor;

it('converts decimals to minor units and back', function (string $decimal, int $minor) {
    expect(Minor::fromDecimal($decimal))->toBe($minor)
        ->and(Minor::toDecimal($minor))->toBe(number_format($minor / 100, 2, '.', ''));
})->with([
    ['0', 0],
    ['12.5', 1250],
    ['1234.56', 123456],
    ['-15.00', -1500],
    ['99999999.99', 9999999999],
]);

it('rounds half up past two decimals', function () {
    expect(Minor::fromDecimal('10.005'))->toBe(1001)
        ->and(Minor::fromDecimal('10.004'))->toBe(1000)
        ->and(Minor::fromDecimal('-10.005'))->toBe(-1001);
});

it('returns null for empty or broken input', function () {
    expect(Minor::tryFromDecimal(null))->toBeNull()
        ->and(Minor::tryFromDecimal(''))->toBeNull()
        ->and(Minor::tryFromDecimal('12,50'))->toBeNull();
});
