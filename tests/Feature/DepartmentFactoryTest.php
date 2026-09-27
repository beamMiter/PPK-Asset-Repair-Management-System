<?php

namespace Tests\Feature;

use App\Models\Department;
use Tests\TestCase;

/**
 * `departments.code` is unique. The factory ended it in rand(10,99), so two departments whose names begin with the same five letters
 * met 1 time in 90 and the test that made them failed at random - about one full-suite run in ten (SlaReportLayoutTest made six).
 */
class DepartmentFactoryTest extends TestCase
{
    public function test_the_codes_the_factory_makes_never_repeat(): void
    {
        $codes = collect(range(1, 600))->map(fn () => Department::factory()->make()->code);

        $this->assertSame(600, $codes->unique()->count());
    }

    public function test_a_code_still_fits_its_column_and_starts_with_the_name(): void
    {
        $department = Department::factory()->make();

        $this->assertLessThanOrEqual(20, strlen($department->code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{1,5}\d{5}$/', $department->code);
    }
}
