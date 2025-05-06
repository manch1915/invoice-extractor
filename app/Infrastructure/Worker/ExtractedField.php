<?php

declare(strict_types=1);

namespace App\Infrastructure\Worker;

final readonly class ExtractedField
{
    /** @param  list<float>|null  $bbox */
    public function __construct(
        public ?string $value,
        public ?string $raw,
        public float $confidence,
        public ?int $page,
        public ?array $bbox,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $value = $data['value'] ?? null;

        return new self(
            value: $value === null || $value === '' ? null : (string) $value,
            raw: isset($data['raw']) ? mb_substr((string) $data['raw'], 0, 255) : null,
            confidence: max(0.0, min(1.0, (float) ($data['confidence'] ?? 0))),
            page: isset($data['page']) ? (int) $data['page'] : null,
            bbox: is_array($data['bbox'] ?? null) ? array_map('floatval', array_values($data['bbox'])) : null,
        );
    }

    public static function missing(): self
    {
        return new self(null, null, 0.0, null, null);
    }
}
