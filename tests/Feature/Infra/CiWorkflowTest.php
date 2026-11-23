<?php

namespace Tests\Feature\Infra;

use Tests\TestCase;

/**
 * The tests ran only on a developer's machine, so a pull request could be merged red. `.github/workflows/tests.yml` runs both
 * suites on every pull request and every push to dev. These cases keep it honest: it runs what the README calls the tests,
 * on the same PHP, Node and database as the Docker setup, against the database phpunit.xml names.
 */
class CiWorkflowTest extends TestCase
{
    private function workflow(): string
    {
        $path = base_path('.github/workflows/tests.yml');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_it_runs_both_suites_on_every_pull_request_and_push_to_dev(): void
    {
        $yml = $this->workflow();

        $this->assertMatchesRegularExpression('/^on:\s*\n(?:\s+.*\n)*?\s+pull_request:/m', $yml);
        $this->assertMatchesRegularExpression('/^\s+push:\s*\n\s+branches:\s*\[\s*dev\s*\]/m', $yml);
        $this->assertStringContainsString('run: php artisan test', $yml);
        $this->assertStringContainsString('run: npm run test:js', $yml);
    }

    public function test_it_builds_the_assets_the_way_the_docker_image_does_after_both_suites(): void
    {
        $yml = $this->workflow();

        // .docker/Dockerfile runs `npm run build`; a pull request that broke it (vite.config.js, a CSS file, an import) was green here and
        // failed only when the image was built
        preg_match('/^RUN (npm run build)$/m', (string) file_get_contents(base_path('.docker/Dockerfile')), $dockerBuild);
        $this->assertSame('npm run build', $dockerBuild[1] ?? null, 'the Docker image still builds with npm run build');
        $this->assertStringContainsString('run: npm run build', $yml);

        // after the suites, not before: a built public/build/manifest.json must not change what the tests see
        $this->assertGreaterThan(strpos($yml, 'run: npm run test:js'), strpos($yml, 'run: npm run build'));
        $this->assertGreaterThan(strpos($yml, 'run: php artisan test'), strpos($yml, 'run: npm run build'));
    }

    public function test_it_holds_the_php_a_pull_request_changes_to_pint_before_the_suites_run(): void
    {
        $yml = $this->workflow();

        // only the files the pull request changes, never the whole project: most of the code is older than Pint, and one 200-file change would
        // bury every review; the old files are brought in line one at a time, by whoever next edits them
        $this->assertStringContainsString('vendor/bin/pint --test --diff="origin/$BASE_REF"', $yml);
        $this->assertStringContainsString('BASE_REF: ${{ github.base_ref }}', $yml, 'the branch name goes through env, not into the script');
        // `pint --diff=origin/dev` asks git for `origin/dev...HEAD`, which needs the merge base: on the one-commit checkout it finds none and Pint
        // answers "the [--diff] option is only available when using Git" (the first run of this check did)
        $this->assertMatchesRegularExpression('/uses: actions\/checkout@v4\s*\n\s+with:\s*\n(?:\s+#.*\n)*\s+fetch-depth: 0\b/', $yml, 'the whole history is checked out');
        $this->assertStringContainsString("if: github.event_name == 'pull_request'", $yml, 'a push has no base to compare with');

        // it needs vendor/ (so after composer install) and is quick (so before the suites)
        $pint = strpos($yml, 'vendor/bin/pint');
        $this->assertGreaterThan(strpos($yml, 'run: composer install'), $pint);
        $this->assertLessThan(strpos($yml, 'run: php artisan test'), $pint);

        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $this->assertArrayHasKey('laravel/pint', $composer['require-dev']);
    }

    public function test_it_uses_the_php_node_and_database_of_the_docker_setup(): void
    {
        $yml = $this->workflow();

        preg_match('/^ARG PHP_VERSION=(\S+)$/m', (string) file_get_contents(base_path('.docker/Dockerfile')), $php);
        preg_match('/^FROM node:(\d+)/m', (string) file_get_contents(base_path('.docker/Dockerfile')), $node);
        preg_match('/^\s+image:\s*(mariadb:\S+)$/m', (string) file_get_contents(base_path('docker-compose.yml')), $db);
        preg_match('/^\s+image:\s*(redis:\S+)$/m', (string) file_get_contents(base_path('docker-compose.yml')), $redis);

        $this->assertStringContainsString("php-version: '{$php[1]}'", $yml);
        $this->assertStringContainsString("node-version: {$node[1]}", $yml);
        $this->assertStringContainsString("image: {$db[1]}", $yml);
        // with phpredis installed, /api/health pings Redis and answers 503 without it (it did, on the first run)
        $this->assertStringContainsString("image: {$redis[1]}", $yml);
        $this->assertMatchesRegularExpression('/extensions:.*\bredis\b/', $yml);
    }

    public function test_its_database_is_the_one_phpunit_xml_names(): void
    {
        $phpunit = simplexml_load_file(base_path('phpunit.xml'));
        $name = (string) $phpunit->xpath('//env[@name="DB_DATABASE"]')[0]['value'];

        $this->assertStringContainsString("MARIADB_DATABASE: {$name}", $this->workflow());
    }

    public function test_it_creates_the_folders_git_does_not_keep(): void
    {
        // storage/framework/views is where Blade compiles to: without it every page test fails on a fresh checkout
        $this->assertStringContainsString('storage/framework/views', $this->workflow());
        // an empty .env: without one, phpdotenv's attempt to read it is a warning on every test (1067 on the first run)
        $this->assertStringContainsString('touch .env', $this->workflow());
    }
}
