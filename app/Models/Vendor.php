<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'normalized_name', 'tax_id', 'country', 'default_category_id'])]
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    /** @return HasOne<CategoryRule, $this> */
    public function rule(): HasOne
    {
        return $this->hasOne(CategoryRule::class);
    }

    /** "ООО «Мельница Север»", "Мельница Север ООО" and "MELNITSA SEVER" should meet in one place. */
    public static function normalize(string $name): string
    {
        $name = mb_strtolower($name);
        $name = (string) preg_replace('/[«»"\'`.,()]/u', ' ', $name);
        $name = (string) preg_replace(
            '/(*UCP)\b(ооо|оао|зао|пао|ао|ип|llc|ltd|limited|inc|gmbh|plc|corp|co)\b/u',
            ' ',
            $name,
        );

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }
}
