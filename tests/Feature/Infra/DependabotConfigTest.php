<?php

namespace Tests\Feature\Infra;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Nobody ran `composer audit` or `npm audit` unless they remembered to, so a new advisory was found by chance. `.github/dependabot.yml`
 * has Dependabot open the update pull requests, which the test workflow then checks like any other. The one thing it must never get
 * wrong: the repository's default branch is `main`, and a Dependabot pull request goes to the default branch unless told otherwise.
 */
class DependabotConfigTest extends TestCase
{
    private function config(): array
    {
        $path = base_path('.github/dependabot.yml');
        $this->assertFileExists($path);

        return Yaml::parse((string) file_get_contents($path));
    }

    public function test_it_watches_php_packages_node_packages_and_the_workflow_actions(): void
    {
        $config = $this->config();
        $this->assertSame(2, $config['version']);

        $ecosystems = collect($config['updates'])->pluck('package-ecosystem')->sort()->values()->all();
        $this->assertSame(['composer', 'github-actions', 'npm'], $ecosystems);

        foreach ($config['updates'] as $update) {
            $this->assertSame('/', $update['directory'], $update['package-ecosystem']);
            $this->assertSame('weekly', $update['schedule']['interval'], $update['package-ecosystem']);
        }
    }

    public function test_every_update_goes_to_dev_and_never_to_the_default_branch(): void
    {
        foreach ($this->config()['updates'] as $update) {
            $this->assertSame('dev', $update['target-branch'] ?? null, "{$update['package-ecosystem']} must target dev explicitly: the default branch is main");
        }
    }

    public function test_its_pull_requests_are_named_like_the_rest_of_the_history(): void
    {
        foreach ($this->config()['updates'] as $update) {
            $this->assertSame('chore(deps)', $update['commit-message']['prefix'] ?? null, $update['package-ecosystem']);
        }
    }

    public function test_it_does_not_open_a_flood_of_pull_requests_for_one_week_of_updates(): void
    {
        foreach ($this->config()['updates'] as $update) {
            $this->assertLessThanOrEqual(5, $update['open-pull-requests-limit'] ?? 99, $update['package-ecosystem']);
        }
    }
}
