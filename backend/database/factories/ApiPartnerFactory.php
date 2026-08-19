<?php

namespace Database\Factories;

use App\Models\ApiPartner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApiPartnerFactory extends Factory
{
    protected $model = ApiPartner::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'client_id' => Str::random(24),
            'client_secret' => \Illuminate\Support\Facades\Hash::make('secret-'.Str::random(24)),
            'scopes' => ['health_record:read'],
            'quota_per_minute' => 60,
            'is_active' => true,
        ];
    }

    /**
     * Disable the partner so tokens and data access are rejected.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}