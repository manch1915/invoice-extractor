<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'vendor_id', 'pattern', 'source', 'hits', 'last_matched_at', 'created_by'])]
class CategoryRule extends Model
{
    public const SOURCE_LEARNED = 'learned';

    public const SOURCE_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'last_matched_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
