<?php

declare(strict_types=1);

namespace App\Services\Exports;

final readonly class ExportResult
{
    /** @param  array<string, string>  $externalIds  document id => id in the other system */
    public function __construct(
        public ?string $filePath = null,
        public ?string $externalUrl = null,
        public array $externalIds = [],
    ) {}
}
