<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pages render their @vite tags from a hot file of the tests' own, so a result never depends on this machine: with
     * Vite stopped and an old `public/build`, every page answered 500 on a missing manifest entry, and a fresh build
     * renders hashed names where tests look for the source paths.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $app->make(Vite::class)->useHotFile($app->basePath('tests/fixtures/vite-hot'));

        return $app;
    }
}
