<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * public/vendor holds the third-party files the pages used to take from CDNs. A missing file is a page without its layout,
 * icons or fonts, and a stylesheet that still points at the internet is the old problem back: both are checked here.
 */
class VendoredFilesTest extends TestCase
{
    public function test_every_vendored_file_a_view_names_is_there(): void
    {
        $named = [];
        foreach (File::allFiles(resource_path('views')) as $view) {
            preg_match_all("#asset\('(vendor/[^']+)'\)#", $view->getContents(), $m);
            foreach ($m[1] as $path) {
                $named[$path] = $view->getRelativePathname();
            }
        }

        $this->assertNotEmpty($named, 'the views should name their vendored files with asset(\'vendor/...\')');
        foreach ($named as $path => $view) {
            $this->assertFileExists(public_path($path), "$view names $path");
        }
    }

    public function test_every_font_a_vendored_stylesheet_names_is_there_and_none_comes_from_the_internet(): void
    {
        $sheets = array_filter(File::allFiles(public_path('vendor')), fn ($f) => $f->getExtension() === 'css');
        $this->assertNotEmpty($sheets);

        foreach ($sheets as $sheet) {
            $css = $sheet->getContents();
            $this->assertDoesNotMatchRegularExpression('#url\(\s*[\'"]?(https?:)?//#i', $css, $sheet->getRelativePathname().' still loads from the internet');
            $this->assertDoesNotMatchRegularExpression('#@import\s#i', $css, $sheet->getRelativePathname());

            preg_match_all('#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#', $css, $m);
            foreach ($m[1] as $ref) {
                if (str_starts_with($ref, 'data:') || str_starts_with($ref, '#')) {
                    continue;
                }
                $file = realpath($sheet->getPath().'/'.preg_replace('/[?#].*$/', '', $ref));
                $this->assertNotFalse($file, $sheet->getRelativePathname()." names $ref, which is not there");
            }
        }
    }

    public function test_the_vendor_folder_says_where_each_file_comes_from_and_its_licence(): void
    {
        $readme = (string) file_get_contents(public_path('vendor/README.md'));

        foreach (File::directories(public_path('vendor')) as $dir) {
            $name = basename($dir);
            if ($name === 'fonts') {
                foreach (File::directories($dir) as $font) {
                    $this->assertStringContainsString(basename($font), $readme, 'vendor/fonts/'.basename($font).' is not listed');
                }
                continue;
            }
            $this->assertStringContainsString($name, $readme, "vendor/$name is not listed");
        }
        $this->assertMatchesRegularExpression('/licen[cs]e/i', $readme);
    }
}
