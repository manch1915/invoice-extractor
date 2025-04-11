<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $word = $this->faker->unique()->word();

        return [
            'code' => $word,
            'name_en' => ucfirst($word),
            'name_ru' => ucfirst($word),
            'color' => '#2b6cb0',
            'account_code' => '429',
            'keywords' => [],
        ];
    }
}
