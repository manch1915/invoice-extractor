<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Enums\CategorySource;
use App\Models\Category;

final readonly class CategoryChoice
{
    public function __construct(
        public Category $category,
        public CategorySource $source,
    ) {}
}
