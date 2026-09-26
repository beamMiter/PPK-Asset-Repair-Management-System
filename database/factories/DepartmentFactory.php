<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * Counts the departments made in this process: the code below used to end in rand(10,99), so two names that begin with the same five
     * letters met 1 time in 90 - `departments.code` is unique, and a test that made several departments failed at random (one run in
     * about ten of the whole suite).
     */
    private static int $sequence = 0;

    // กำหนดค่าเริ่มต้นให้กับ Model Department
    public function definition(): array
    {
        $nameTh = $this->faker->unique()->company();
        $nameEn = Str::title($nameTh);
        return [
            // รหัสแผนก: ตัวย่อ 5 ตัว + เลขลำดับ 5 หลัก (ไม่ซ้ำกันแน่นอน)
            'code'    => strtoupper(Str::substr(Str::slug($nameTh,''),0,5)) . sprintf('%05d', ++self::$sequence),
            'name_th' => $nameTh,
            'name_en' => $nameEn,
        ];
    }
}
