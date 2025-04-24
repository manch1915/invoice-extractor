<?php

declare(strict_types=1);

use App\Domain\Validation\DocumentFacts;
use App\Domain\Validation\DocumentValidator;
use App\Domain\Validation\Issue;
use App\Enums\IssueCode;
use App\Enums\IssueSeverity;

function facts(array $overrides = []): DocumentFacts
{
    return new DocumentFacts(...array_merge([
        'vendorName' => 'ООО «КанцМаркет»',
        'vendorTaxId' => '7719053389',
        'documentNumber' => 'КМ-21688',
        'issueDate' => new DateTimeImmutable('2026-09-29'),
        'dueDate' => new DateTimeImmutable('2026-10-06'),
        'currency' => 'RUB',
        'subtotal' => 666_700,
        'taxRate' => '20',
        'taxAmount' => 133_340,
        'total' => 800_040,
        'itemAmounts' => [319_200, 152_000, 129_000, 199_840],
        'today' => new DateTimeImmutable('2026-10-19'),
    ], $overrides));
}

/** @return list<string> */
function codes(DocumentFacts $facts): array
{
    return array_map(fn (Issue $i) => $i->code->value, (new DocumentValidator)->validate($facts));
}

it('passes a consistent document', function () {
    expect(codes(facts()))->toBe([]);
});

it('flags totals that do not add up', function () {
    expect(codes(facts(['total' => 800_140])))->toContain(IssueCode::TotalsMismatch->value);
});

it('flags line items that sum to something else', function () {
    expect(codes(facts(['itemAmounts' => [100_000, 200_000]])))->toContain(IssueCode::ItemsMismatch->value);
});

it('allows a kopeck of rounding per line', function () {
    expect(codes(facts(['itemAmounts' => [319_201, 152_001, 129_000, 199_841]])))->not->toContain(IssueCode::ItemsMismatch->value);
});

it('accepts lines that add up to the net amount', function () {
    expect(codes(facts(['itemAmounts' => [666_700]])))->toBe([]);
});

it('catches VAT charged at the wrong rate', function () {
    // 20% of the gross total instead of 20/120: a classic mistake
    $issues = (new DocumentValidator)->validate(facts([
        'subtotal' => 186_912_00, 'taxAmount' => 46_728_00, 'total' => 233_640_00, 'itemAmounts' => [],
    ]));

    $tax = collect($issues)->first(fn (Issue $i) => $i->code === IssueCode::TaxMismatch);
    expect($tax)->not->toBeNull()
        ->and($tax->severity)->toBe(IssueSeverity::Blocking)
        ->and($tax->context['expected'])->toBe(37_382_40);
});

it('rejects an INN with a broken check digit', function () {
    expect(codes(facts(['vendorTaxId' => '7728510344'])))->toBe([IssueCode::InvalidTaxId->value]);
});

it('stops documents dated in the future', function () {
    expect(codes(facts(['issueDate' => new DateTimeImmutable('2027-10-15'), 'dueDate' => null])))
        ->toBe([IssueCode::FutureDate->value]);
});

it('warns when the due date is before the issue date', function () {
    $issues = (new DocumentValidator)->validate(facts(['dueDate' => new DateTimeImmutable('2026-09-01')]));

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe(IssueCode::DueBeforeIssue)
        ->and($issues[0]->severity)->toBe(IssueSeverity::Warning);
});

it('blocks suspected duplicates and points to the original', function () {
    $issues = (new DocumentValidator)->validate(facts(['duplicateIds' => ['01JA0000000000000000000001']]));

    expect($issues[0]->code)->toBe(IssueCode::Duplicate)
        ->and($issues[0]->context['original_id'])->toBe('01JA0000000000000000000001');
});

it('notices a bill far above the usual for the vendor', function () {
    $issues = (new DocumentValidator)->validate(facts(['vendorHistory' => [210_000, 190_000, 200_000]]));

    expect($issues[0]->code)->toBe(IssueCode::UnusualAmount)
        ->and($issues[0]->severity)->toBe(IssueSeverity::Warning)
        ->and($issues[0]->context['ratio'])->toBe(4.0);
});

it('requires the fields every ledger entry needs', function () {
    $issues = (new DocumentValidator)->validate(facts([
        'vendorName' => null, 'total' => null, 'documentNumber' => null, 'itemAmounts' => [],
    ]));

    $blocking = collect($issues)->filter(fn (Issue $i) => $i->severity === IssueSeverity::Blocking)
        ->map(fn (Issue $i) => $i->field?->value)->values()->all();
    expect($blocking)->toBe(['vendor_name', 'total'])
        ->and(collect($issues)->contains(fn (Issue $i) => $i->field?->value === 'document_number' && $i->severity === IssueSeverity::Warning))->toBeTrue();
});

it('asks for a category when none was found', function () {
    expect(codes(facts(['hasCategory' => false])))->toBe([IssueCode::NoCategory->value]);
});
