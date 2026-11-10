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
