<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ?list<string> $keywords
 */
#[Fillable(['code', 'name_en', 'name_ru', 'color', 'account_code', 'account_1c', 'keywords'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['keywords' => 'array'];
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<CategoryRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(CategoryRule::class);
    }

    public function name(string $locale): string
    {
        return $locale === 'ru' ? $this->name_ru : $this->name_en;
    }
}
