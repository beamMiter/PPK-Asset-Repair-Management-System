<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `resources/js/app.js` is loaded by every page, and `repair/dashboard.js` is part of it (its `turbo:load` listener has
 * to exist before Turbo fires the first event). It used to `import Chart from 'chart.js/auto'` — 200 kB of charting
 * library shipped to every page, the login page included, for the three dashboards that draw charts.
 */
class JsBundleTest extends TestCase
{
    /** Every JS file reachable from the entry through static `import … from './x'` statements. */
    private function staticImportGraph(string $entry): array
    {
        $seen = [];
        $queue = [$entry];

        while ($queue) {
            $file = array_shift($queue);
            if (isset($seen[$file])) {
                continue;
            }

            $code = (string) file_get_contents(base_path($file));
            // drop comments, so a mention in a comment is not an import; `import(` (dynamic) never matches the pattern below
            $code = preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $code);
            $seen[$file] = $code;

            preg_match_all('/^\s*import\s+(?:[^\'";]*?\s+from\s+)?[\'"]([^\'"]+)[\'"]/m', $code, $m);
            foreach ($m[1] as $specifier) {
                if (! str_starts_with($specifier, '.')) {
                    continue;
                }
                $base = dirname($file).'/'.$specifier;
                foreach ([$base, $base.'.js', $base.'/index.js'] as $candidate) {
                    $real = realpath(base_path($candidate));
                    if ($real && is_file($real) && str_ends_with($real, '.js')) {
                        $queue[] = ltrim(str_replace(base_path(), '', $real), '/');
                        break;
                    }
                }
            }
        }

        return $seen;
    }

    public function test_the_main_bundle_does_not_pull_in_chart_js(): void
    {
        $graph = $this->staticImportGraph('resources/js/app.js');

        $this->assertArrayHasKey('resources/js/repair/dashboard.js', $graph, 'the walk should reach the dashboard module');

        $offenders = [];
        foreach ($graph as $file => $code) {
            if (preg_match('/^\s*import\s+(?:[^\'";]*?\s+from\s+)?[\'"]chart\.js[^\'"]*[\'"]/m', $code)) {
                $offenders[] = $file;
            }
        }

        $this->assertSame([], $offenders, 'load Chart.js with import(\'chart.js/auto\') where a chart is drawn');
    }

    public function test_the_dashboard_module_loads_chart_js_on_demand_and_only_for_pages_with_a_chart(): void
    {
        $code = file_get_contents(base_path('resources/js/repair/dashboard.js'));

        $this->assertStringContainsString("import('chart.js/auto')", $code);
        $this->assertMatchesRegularExpression('/CHART_CANVASES\.some\(safeGet\)\)\s*return/', $code, 'a page without a chart must not fetch the library');
    }

    public function test_the_page_specific_chart_bundles_are_separate_vite_entries(): void
    {
        $vite = file_get_contents(base_path('vite.config.js'));

        foreach (['resources/js/settings/sla/dashboard.js', 'resources/js/maintenance/rating/technicians-dashboard.js'] as $entry) {
            $this->assertStringContainsString($entry, $vite);
        }
    }
}
