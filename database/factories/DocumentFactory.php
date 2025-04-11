<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentSource;
use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $id = (string) Str::ulid();

        return [
            'id' => $id,
            'status' => DocumentStatus::Ready,
            'source' => DocumentSource::Upload,
            'original_filename' => 'invoice-'.$this->faker->numberBetween(100, 999).'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 48_000,
            'storage_path' => "originals/{$id}.pdf",
            'sha256' => hash('sha256', $id),
            'doc_type' => 'invoice',
            'language' => 'ru',
            'engine' => 'rules',
            'vendor_name' => 'ООО «КанцМаркет»',
            'vendor_tax_id' => '7719053389',
            'document_number' => 'КМ-'.$this->faker->unique()->numberBetween(10000, 99999),
            'issue_date' => '2026-09-29',
            'currency' => 'RUB',
            'subtotal' => 666_700,
            'tax_rate' => '20',
            'tax_amount' => 133_340,
            'total' => 800_040,
            'confidence' => 0.95,
        ];
    }
}
