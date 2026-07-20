<?php

declare(strict_types=1);

namespace Modules\Store\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Store\Models\Product;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => [
                'en' => $this->faker->words(3, true),
                'id' => $this->faker->words(3, true),
            ],
            'description' => [
                'en' => $this->faker->sentence(),
                'id' => $this->faker->sentence(),
            ],
            'stock' => $this->faker->numberBetween(10, 100),
            'price' => $this->faker->randomFloat(2, 5, 100),
            'flash_sale_price' => null,
            'flash_sale_start' => null,
            'flash_sale_end' => null,
            'is_active' => true,
        ];
    }
}
