<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $schoolNames = [
            'SMAN_1_DENPASAR' => 'SMA Negeri 1 Denpasar',
            'SMAN_2_DENPASAR' => 'SMA Negeri 2 Denpasar',
            'SMAN_3_DENPASAR' => 'SMA Negeri 3 Denpasar',
            'SMAK_1_DENPASAR' => 'SMA Katolik 1 Denpasar',
            'SMA_ISTIQOMAH' => 'SMA Istiqomah Denpasar',
        ];

        $name = $this->faker->unique()->randomElement(array_keys($schoolNames));

        return [
            'name' => $name,
            'display_name' => $schoolNames[$name],
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'is_active' => true,
        ];
    }

    /**
     * Create an inactive school
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a school with specific name
     */
    public function withName(string $name, string $displayName = null): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'display_name' => $displayName ?? $name,
        ]);
    }

    /**
     * Create school without email
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
        ]);
    }

    /**
     * Create school without phone
     */
    public function withoutPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => null,
        ]);
    }
}