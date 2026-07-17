<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return ['supplier_code' => fake()->unique()->numerify('SUP-####'), 'name' => fake()->name(), 'company_name' => fake()->company(), 'phone' => fake()->phoneNumber(), 'email' => fake()->safeEmail(), 'address' => fake()->address(), 'city' => fake()->city(), 'province' => fake()->state(), 'is_active' => true];
    }
}
