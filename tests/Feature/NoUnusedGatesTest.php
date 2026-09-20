<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * `tech-only` was defined twice (AppServiceProvider: technician/admin, AuthServiceProvider: admin/supervisor/worker) and
 * — like `admin-only` — never checked anywhere; whichever registered last silently won. Every gate that exists must be
 * used by some route, controller, policy or view.
 */
class NoUnusedGatesTest extends TestCase
{
    public function test_every_defined_gate_is_referenced_somewhere(): void
    {
        $sources = collect([base_path('app'), base_path('routes'), base_path('resources/views')])
            ->flatMap(fn ($dir) => iterator_to_array(\Symfony\Component\Finder\Finder::create()->files()->in($dir)->name('*.php'), false))
            ->reject(fn ($f) => str_ends_with($f->getFilename(), 'ServiceProvider.php'))
            ->map(fn ($f) => $f->getContents())
            ->implode("\n");

        // policy abilities are resolved by name from the policy classes, not defined as plain gates
        $unused = collect(array_keys(Gate::abilities()))
            ->reject(fn ($ability) => preg_match('/(?<![\w-])'.preg_quote($ability, '/').'(?![\w-])/', $sources) === 1)
            ->values()
            ->all();

        // `view-repair-dashboard` is kept until the member-dashboard access decision is made
        $this->assertSame(['view-repair-dashboard'], $unused);
    }
}
