<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'permissions' => [],
            'is_super' => false,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['name' => 'Owner', 'is_super' => true]);
    }

    public function manager(): static
    {
        return $this->state(fn () => ['name' => 'Manager', 'permissions' => Role::ManagerPermissions]);
    }
}
