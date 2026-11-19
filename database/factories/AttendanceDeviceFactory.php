<?php

namespace Database\Factories;

use App\Models\AttendanceDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceDevice>
 */
class AttendanceDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'serial_number' => 'TEST'.fake()->unique()->numerify('######'),
            'is_approved' => true,
        ];
    }
}
