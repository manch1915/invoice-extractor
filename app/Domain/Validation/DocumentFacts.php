<?php

declare(strict_types=1);

namespace App\Domain\Validation;

use DateTimeImmutable;

/**
 * Everything the checks need to know about a document, without the database.
 */
final readonly class DocumentFacts
{
    /**
     * @param  list<?int>  $itemAmounts  line amounts in minor units
     * @param  list<int>  $vendorHistory  totals of earlier documents from the same vendor, minor units
     * @param  list<string>  $duplicateIds  documents with the same vendor, number and total
     */
    public function __construct(
        public ?string $vendorName = null,
        public ?string $vendorTaxId = null,
        public ?string $documentNumber = null,
        public ?DateTimeImmutable $issueDate = null,
        public ?DateTimeImmutable $dueDate = null,
        public ?string $currency = null,
        public ?int $subtotal = null,
        public ?string $taxRate = null,
        public ?int $taxAmount = null,
        public ?int $total = null,
        public array $itemAmounts = [],
        public array $vendorHistory = [],
        public array $duplicateIds = [],
        public bool $hasCategory = true,
        public DateTimeImmutable $today = new DateTimeImmutable('today'),
    ) {}
}
