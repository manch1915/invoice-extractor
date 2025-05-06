<?php

declare(strict_types=1);

namespace App\Infrastructure\Worker;

use App\Enums\FieldKey;
use UnexpectedValueException;

/**
 * The worker's answer, checked for shape once so the rest of the app can trust the types.
 */
final readonly class ExtractionResult
{
    /**
     * @param  list<array{number: int, width: int, height: int, image: string, words: list<array{text: string, bbox: list<float>, conf: float}>}>  $pages
     * @param  array<string, ExtractedField>  $fields
     * @param  list<ExtractedItem>  $items
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $docType,
        public string $language,
        public string $engine,
        public string $textSource,
        public float $ocrQuality,
        public int $durationMs,
        public array $pages,
        public array $fields,
        public array $items,
        public ?string $categoryCode,
        public array $warnings,
    ) {}

    /** @param  array<mixed>  $data */
    public static function fromArray(array $data): self
    {
        $meta = $data['document'] ?? null;
        if (! is_array($meta) || ! is_array($data['pages'] ?? null) || ! is_array($data['fields'] ?? null)) {
            throw new UnexpectedValueException('Worker response is missing document, pages or fields');
        }

        $fields = [];
        foreach (FieldKey::cases() as $key) {
            $raw = $data['fields'][$key->value] ?? null;
            $fields[$key->value] = is_array($raw) ? ExtractedField::fromArray($raw) : ExtractedField::missing();
        }

        $pages = [];
        foreach ($data['pages'] as $page) {
            $pages[] = [
                'number' => (int) $page['number'],
                'width' => (int) $page['width'],
                'height' => (int) $page['height'],
                'image' => (string) $page['image'],
                'words' => array_values(array_map(fn (array $w) => [
                    'text' => (string) $w['text'],
                    'bbox' => array_values(array_map('floatval', $w['bbox'])),
                    'conf' => (float) $w['conf'],
                ], $page['words'] ?? [])),
            ];
        }

        $items = array_values(array_map(
            fn (array $item) => ExtractedItem::fromArray($item),
            is_array($data['line_items'] ?? null) ? $data['line_items'] : [],
        ));

        $category = $data['category'] ?? null;

        return new self(
            docType: (string) ($meta['doc_type'] ?? 'invoice'),
            language: (string) ($meta['language'] ?? 'en'),
            engine: (string) ($meta['engine'] ?? 'rules'),
            textSource: (string) ($meta['source'] ?? 'ocr'),
            ocrQuality: (float) ($meta['ocr_quality'] ?? 0),
            durationMs: (int) ($meta['duration_ms'] ?? 0),
            pages: $pages,
            fields: $fields,
            items: $items,
            categoryCode: is_array($category) ? (string) $category['code'] : null,
            warnings: array_values(array_map('strval', $data['warnings'] ?? [])),
        );
    }

    public function field(FieldKey $key): ExtractedField
    {
        return $this->fields[$key->value];
    }
}
