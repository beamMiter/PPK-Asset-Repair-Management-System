<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A file name is whatever the person's disk holds: on macOS and Linux `<img src=x onerror=…>.jpg` is a legal name. The upload
 * previews used to put it into `innerHTML` as it was, so choosing such a file ran its script inside the page. A name goes in as
 * text (`textContent`), never as part of a markup string.
 *
 * (A static scan: the previews are inline scripts in Blade views, out of reach of the JS test suite.)
 */
class FileNamesAreTextTest extends TestCase
{
    /** @return array<string, string[]> file => the offending lines */
    private function markupWithAName(): array
    {
        $found = [];
        $files = collect([...File::allFiles(resource_path('views')), ...File::allFiles(resource_path('js'))])
            ->filter(fn ($f) => in_array($f->getExtension(), ['php', 'js'], true));

        foreach ($files as $file) {
            foreach (preg_split('/\R/', $file->getContents()) as $n => $line) {
                // `>${f.name}</span>` — an interpolated name between tags inside a template literal
                if (preg_match('/>\s*\$\{\s*[\w.]*(?:\.name|filename|original_name)\s*\}\s*</', $line)) {
                    $found[$file->getRelativePathname()][] = ($n + 1) . ': ' . trim($line);
                }
            }
        }

        return $found;
    }

    public function test_no_preview_builds_markup_around_a_file_name(): void
    {
        $this->assertSame([], $this->markupWithAName(), 'a file name is put into innerHTML as it is');
    }

    public function test_the_three_previews_set_the_name_as_text(): void
    {
        foreach ([
            'maintenance/requests/partials/_attachments.blade.php',
            'maintenance/requests/_form.blade.php',
            'assets/_form.blade.php',
        ] as $view) {
            $source = file_get_contents(resource_path('views/' . $view));

            $this->assertStringContainsString('data-file-name', $source, $view);
            $this->assertMatchesRegularExpression("/querySelector\\('\\[data-file-name\\]'\\)\\.textContent = f\\.name/", $source, $view);
        }
    }
}
