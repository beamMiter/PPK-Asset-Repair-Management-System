<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssetCategoryFactory extends Factory
{
    // กำหนดค่าเริ่มต้นให้กับ Model AssetCategory
    public function definition(): array
    {
        // รายชื่อหมวดหมู่คอมพิวเตอร์และอุปกรณ์เบื้องต้น
        $name = fake()->unique()->randomElement([
            'Computer', 'Printer', 'Air Conditioner', 'Network', 'Furniture'
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->optional()->hexColor(),
            'is_active' => true,
        ];
    }
}
