<?php

namespace Tests\Feature\Infra;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The container setup (docker-compose.yml, .docker/*) is read, not run, here: nothing in the PHP tests exercised it, so it had drifted from
 * what the app needs - no scheduler (the chat purge and the token prune never ran), no php.ini (uploads capped at 2 MB while the app promises
 * 10 MB, and public/.user.ini turned the memory limit off), nginx serving dotfiles and uploaded files with no protection.
 */
class DockerInfraTest extends TestCase
{
    private function file(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    /** "12M" / "512M" / "40m" / "1G" -> kilobytes */
    private function kb(string $size): int
    {
        preg_match('/^(\d+)\s*([kmg])?/i', trim($size), $m);
        $n = (int) $m[1];

        return match (strtolower($m[2] ?? 'k')) {
            'g' => $n * 1024 * 1024,
            'm' => $n * 1024,
            default => $n,
        };
    }

    private function ini(string $key): string
    {
        preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.+?)\s*(?:;.*)?$/m', $this->file('.docker/php.ini'), $m);

        return $m[1] ?? '';
    }

    // ---- the scheduler ---------------------------------------------------------------------------------------------

    public function test_a_scheduler_runs_the_apps_scheduled_work(): void
    {
        $compose = Yaml::parseFile(base_path('docker-compose.yml'));
        $scheduler = $compose['services']['scheduler'] ?? null;

        $this->assertNotNull($scheduler, 'without it chat:purge-deleted and sanctum:prune-expired never run');
        $this->assertSame('php artisan schedule:work', $scheduler['command']);
        $this->assertSame($compose['services']['app']['image'], $scheduler['image'], 'the same image as the app');
        $this->assertSame('service_healthy', $scheduler['depends_on']['app']['condition'], 'after the migrations');
        $this->assertSame('arm-scheduler', $scheduler['container_name']);
    }

    public function test_what_the_scheduler_has_to_run_is_scheduled(): void
    {
        $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($e) => (string) $e->command)->implode(' | ');

        $this->assertStringContainsString('chat:purge-deleted', $scheduled);
        $this->assertStringContainsString('sanctum:prune-expired', $scheduled);
    }

    // ---- php.ini ---------------------------------------------------------------------------------------------------

    public function test_the_image_carries_a_php_ini_and_the_old_unlimited_memory_file_is_gone(): void
    {
        $this->assertStringContainsString('COPY .docker/php.ini /usr/local/etc/php/conf.d/', $this->file('.docker/Dockerfile'));
        $this->assertFileDoesNotExist(public_path('.user.ini'), 'memory_limit = -1 for every request, in the web root');
    }

    public function test_the_upload_limits_hold_what_the_app_promises(): void
    {
        $perFile = (int) config('uploads.max_kb');   // 10 MB
        $files = 3;                                  // a request takes at most 3 files (MaintenanceAttachmentController)

        $this->assertGreaterThanOrEqual($perFile, $this->kb($this->ini('upload_max_filesize')), 'a 10 MB photo must get through PHP');
        $this->assertGreaterThanOrEqual($perFile * $files, $this->kb($this->ini('post_max_size')), '3 files of 10 MB in one form');
        $this->assertGreaterThanOrEqual($files, (int) $this->ini('max_file_uploads'));
    }

    public function test_nginx_takes_exactly_what_php_takes_in_one_form(): void
    {
        preg_match('/client_max_body_size\s+(\S+?);/', $this->file('.docker/nginx.conf'), $m);

        $this->assertNotEmpty($m);
        $this->assertSame($this->kb($this->ini('post_max_size')), $this->kb($m[1]), 'nginx turns away with 413 what PHP would have taken - or takes what PHP will not');
    }

    public function test_memory_is_capped_not_unlimited_and_the_errors_are_not_shown_to_the_page(): void
    {
        $memory = $this->ini('memory_limit');

        $this->assertNotSame('-1', $memory);
        $this->assertGreaterThanOrEqual(256 * 1024, $this->kb($memory), 'room for a PDF report');
        $this->assertLessThanOrEqual(1024 * 1024, $this->kb($memory));
        $this->assertSame('Off', $this->ini('display_errors'));
        $this->assertSame('Off', $this->ini('expose_php'));
    }

    public function test_opcache_is_installed_and_follows_the_dev_or_production_switch(): void
    {
        $this->assertMatchesRegularExpression('/docker-php-ext-install[^\n]*\bopcache\b/', $this->file('.docker/Dockerfile'));
        $this->assertSame('${PHP_OPCACHE_VALIDATE_TIMESTAMPS:-1}', $this->ini('opcache.validate_timestamps'), 'the variable the Dockerfile already sets, 1 by default');
        $this->assertSame('1', $this->ini('opcache.enable'));
    }

    // ---- nginx -----------------------------------------------------------------------------------------------------

    public function test_nginx_serves_no_file_that_starts_with_a_dot(): void
    {
        $nginx = $this->file('.docker/nginx.conf');

        $this->assertMatchesRegularExpression('#location ~ /\\\\\.\(\?!well-known/\) \{\s*return 404;#', $nginx);
        $this->assertLessThan(strpos($nginx, 'location ~ \.php$'), strpos($nginx, 'location ~ /\.'), 'before the php location, so /.x.php is refused too');
    }

    public function test_an_uploaded_file_is_never_run_as_a_page(): void
    {
        $nginx = $this->file('.docker/nginx.conf');
        $storage = substr($nginx, strpos($nginx, 'location ^~ /storage/'), 500);

        $this->assertStringContainsString('add_header X-Content-Type-Options "nosniff" always;', $storage);
        $this->assertStringContainsString('add_header Content-Security-Policy $storage_csp always;', $storage);
        $this->assertStringContainsString('sandbox', $nginx);
        $this->assertMatchesRegularExpression('/map \$uri \$storage_csp \{\s*~\*\\\\\.pdf\$\s+"";/', $nginx, 'a PDF is left out: Chrome will not open one inside a sandbox');
    }

    public function test_built_files_are_cached_for_a_year_and_compressed_but_pages_are_not(): void
    {
        $nginx = $this->file('.docker/nginx.conf');
        $build = substr($nginx, strpos($nginx, 'location /build/'), 700);

        $this->assertStringContainsString('max-age=31536000, immutable', $build);
        $this->assertStringContainsString('gzip on;', $build);
        $this->assertSame(1, substr_count($nginx, 'gzip on;'), 'not for the pages (they carry a CSRF token: BREACH)');
        $this->assertStringContainsString('server_tokens off;', $nginx);
    }

    // ---- the compose file as a whole ---------------------------------------------------------------------------------

    public function test_every_service_the_app_needs_is_there(): void
    {
        $services = array_keys(Yaml::parseFile(base_path('docker-compose.yml'))['services']);

        foreach (['app', 'web', 'db', 'redis', 'worker', 'scheduler', 'node'] as $service) {
            $this->assertContains($service, $services);
        }
    }
}
