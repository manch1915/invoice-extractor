<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FieldKey;
use App\Enums\FieldStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property FieldKey $key
 * @property FieldStatus $status
 * @property float $confidence
 * @property ?list<float> $bbox
 * @property ?int $page
 */
#[Fillable(['document_id', 'key', 'value', 'original_value', 'raw', 'confidence', 'page', 'bbox', 'status', 'updated_by'])]
class DocumentField extends Model
{
    protected function casts(): array
    {
        return [
            'key' => FieldKey::class,
            'status' => FieldStatus::class,
            'confidence' => 'float',
            'bbox' => 'array',
            'page' => 'integer',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
