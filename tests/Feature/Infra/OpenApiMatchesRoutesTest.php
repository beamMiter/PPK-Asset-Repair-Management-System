<?php

namespace Tests\Feature\Infra;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * `openapi.yaml` is what somebody writing a client (the app, another system) reads, and nothing tied it to the routes: by 2026-11-18 twelve
 * operations were missing from it, among them the three whose rules had just changed (cancel, rating, resubmit). It is not generated, so these
 * cases keep it honest: every API route is documented, nothing documented is gone, and what is written is complete enough to use.
 */
class OpenApiMatchesRoutesTest extends TestCase
{
    private const METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    private function document(): array
    {
        $path = base_path('openapi.yaml');
        $this->assertFileExists($path);

        return Yaml::parse((string) file_get_contents($path));
    }

    private static function normal(string $path): string
    {
        return '/'.trim(preg_replace('/\{[^}]+\}/', '{}', $path), '/');
    }

    /** @return array<int, string> "METHOD /path" for every API route, as the document writes it (without the /api server prefix) */
    private function routes(): array
    {
        $operations = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $operations[] = $method.' '.self::normal(substr($route->uri(), 3));
            }
        }

        sort($operations);

        return array_values(array_unique($operations));
    }

    /** @return array<int, string> */
    private function documented(): array
    {
        $operations = [];

        foreach ($this->document()['paths'] as $path => $item) {
            foreach (self::METHODS as $method) {
                if (isset($item[$method])) {
                    $operations[] = strtoupper($method).' '.self::normal($path);
                }
            }
        }

        sort($operations);

        return array_values(array_unique($operations));
    }

    public function test_it_is_valid_yaml_for_a_strict_parser_too(): void
    {
        // a comma inside an unquoted `{ description: a, b }` is the end of the entry for a strict parser: one line was read as a description
        // cut short plus a few keys of nothing, and a client generator would have choked on it
        $this->assertIsArray($this->document());
        $this->assertSame('3.1.0', $this->document()['openapi']);
    }

    public function test_every_api_route_is_documented(): void
    {
        $missing = array_values(array_diff($this->routes(), $this->documented()));

        $this->assertSame([], $missing, "These routes are in routes/api.php but not in openapi.yaml:\n  ".implode("\n  ", $missing));
    }

    public function test_nothing_documented_is_a_route_that_no_longer_exists(): void
    {
        $gone = array_values(array_diff($this->documented(), $this->routes()));

        $this->assertSame([], $gone, "These are in openapi.yaml but are not routes:\n  ".implode("\n  ", $gone));
    }

    public function test_every_operation_has_a_tag_a_summary_and_its_answers(): void
    {
        $incomplete = [];

        foreach ($this->document()['paths'] as $path => $item) {
            foreach (self::METHODS as $method) {
                if (! isset($item[$method])) {
                    continue;
                }
                $op = $item[$method];
                $label = strtoupper($method)." $path";

                foreach (['tags', 'summary', 'responses'] as $key) {
                    if (empty($op[$key])) {
                        $incomplete[] = "$label has no $key";
                    }
                }
            }
        }

        $this->assertSame([], $incomplete);
    }

    public function test_every_reference_points_at_a_schema_that_exists(): void
    {
        $document = $this->document();
        $schemas = array_keys($document['components']['schemas']);
        $broken = [];

        array_walk_recursive($document, function ($value, $key) use ($schemas, &$broken) {
            if ($key === '$ref' && is_string($value) && preg_match('~^#/components/schemas/(.+)$~', $value, $m) && ! in_array($m[1], $schemas, true)) {
                $broken[] = $value;
            }
        });

        $this->assertSame([], $broken);
    }
}
