<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return ['category_id' => ProductCategory::factory(), 'sku' => fake()->unique()->bothify('SKU-####'), 'name' => fake()->words(3, true), 'unit' => fake()->randomElement(['Pcs', 'Kg', 'Dus', 'Unit']), 'purchase_price' => fake()->numberBetween(1000, 100000), 'average_purchase_price' => 0, 'selling_price' => fake()->numberBetween(100000, 200000), 'stock' => 0, 'minimum_stock' => 5, 'is_active' => true];
    }
}
