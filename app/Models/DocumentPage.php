<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<array{text: string, bbox: list<float>, conf: float}> $words
 */
#[Fillable(['document_id', 'number', 'image_path', 'width', 'height', 'words'])]
#[Hidden(['image_path'])]
#[WithoutTimestamps]
class DocumentPage extends Model
{
    protected function casts(): array
    {
        return [
            'words' => 'array',
            'number' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
