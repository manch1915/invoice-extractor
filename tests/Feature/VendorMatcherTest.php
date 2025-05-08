<?php

declare(strict_types=1);

use App\Models\Vendor;
use App\Services\VendorMatcher;

it('finds a vendor by tax id whatever the spacing', function () {
    $vendor = Vendor::factory()->create(['tax_id' => 'GB284617539']);

    expect(app(VendorMatcher::class)->match('Bakeware Direct', 'gb 284-6175 39')->id)->toBe($vendor->id);
});

it('matches names that differ only in legal form and quotes', function () {
    $vendor = Vendor::factory()->create(['name' => 'ООО «Мельница Север»', 'normalized_name' => Vendor::normalize('ООО «Мельница Север»')]);

    expect(app(VendorMatcher::class)->match('Мельница Север ООО', null)->id)->toBe($vendor->id);
});

it('never rewrites an existing vendor from an unchecked document', function () {
    $vendor = Vendor::factory()->create(['name' => 'Клининг Про', 'normalized_name' => 'клининг про', 'tax_id' => null]);

    $matched = app(VendorMatcher::class)->match('ООО «Клининг Про»', '7730188474');

    expect($matched->id)->toBe($vendor->id)
        ->and($vendor->refresh()->tax_id)->toBeNull();
});

it('keeps a lookalike with another tax id apart from the real vendor', function () {
    $real = Vendor::factory()->create(['name' => 'КанцМаркет', 'normalized_name' => 'канцмаркет', 'tax_id' => '7719053389']);

    $other = app(VendorMatcher::class)->match('ООО «КанцМаркет»', '7728510344');

    expect($other->id)->not->toBe($real->id)
        ->and($other->tax_id)->toBe('7728510344')
        ->and(Vendor::count())->toBe(2);
});
