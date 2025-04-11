<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ?string $quantity
 * @property ?int $unit_price
 * @property ?int $amount
 * @property float $confidence
 * @property ?list<float> $bbox
 */
#[Fillable(['document_id', 'position', 'description', 'quantity', 'unit_price', 'amount', 'confidence', 'page', 'bbox'])]
#[WithoutTimestamps]
class LineItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'integer',
            'amount' => 'integer',
            'confidence' => 'float',
            'bbox' => 'array',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
