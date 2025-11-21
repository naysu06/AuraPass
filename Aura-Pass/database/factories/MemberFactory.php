<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            
            // Generate a random unique ID (like your QR codes)
            // e.g., "MEM-839201"
            'unique_id' => 'MEM-' . strtoupper(Str::random(6)),
            
            // Set expiry date to sometime in the future (1 to 6 months from now)
            'membership_expiry_date' => $this->faker->dateTimeBetween('+1 month', '+6 months'),
            
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}