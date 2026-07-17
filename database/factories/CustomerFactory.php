<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return ['customer_code' => fake()->unique()->numerify('CUS-####'), 'name' => fake()->name(), 'company_name' => fake()->company(), 'phone' => fake()->phoneNumber(), 'email' => fake()->safeEmail(), 'address' => fake()->address(), 'city' => fake()->city(), 'province' => fake()->state(), 'is_active' => true];
    }
}
