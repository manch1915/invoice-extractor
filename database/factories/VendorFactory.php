<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vendor> */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'normalized_name' => Vendor::normalize($name),
            'tax_id' => null,
            'country' => null,
        ];
    }
}
