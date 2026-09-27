<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\EloquentFactories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    // กำหนดค่าเริ่มต้นให้กับ Model User
    public function definition(): array
    {
        // รันหมายเลขบัตรประชาชน (citizen_id) ให้ไม่ซ้ำกันใน Factory
        static $citizenRunning = 2000000000000; // เริ่มต้นที่ 13 หลัก
        $citizenRunning++;

        return [
            'name'              => fake()->name(),
            'citizen_id'        => (string) $citizenRunning,
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            // สามารถกำหนดค่าเริ่มต้นเพิ่มเติมได้ เช่นบทบาท หรือแผนก
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
