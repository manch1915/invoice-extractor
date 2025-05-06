<?php

declare(strict_types=1);

namespace App\Infrastructure\Worker;

final readonly class ExtractedItem
{
    /** @param  list<float>|null  $bbox */
    public function __construct(
        public string $description,
        public ?string $quantity,
        public ?string $unitPrice,
        public ?string $amount,
        public float $confidence,
        public ?int $page,
        public ?array $bbox,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            description: mb_substr(trim((string) ($data['description'] ?? '')), 0, 500),
            quantity: isset($data['quantity']) ? (string) $data['quantity'] : null,
            unitPrice: isset($data['unit_price']) ? (string) $data['unit_price'] : null,
            amount: isset($data['amount']) ? (string) $data['amount'] : null,
            confidence: max(0.0, min(1.0, (float) ($data['confidence'] ?? 0))),
            page: isset($data['page']) ? (int) $data['page'] : null,
            bbox: is_array($data['bbox'] ?? null) ? array_map('floatval', array_values($data['bbox'])) : null,
        );
    }
}
