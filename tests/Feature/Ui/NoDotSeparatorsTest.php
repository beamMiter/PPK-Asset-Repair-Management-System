<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Text that runs two facts together on a line ("… วันหลังปิดงาน - งานที่ใกล้หมดเวลาอยู่บนสุด", "4.0 - พอใจ", the tab title
 * "Maintenance Requests - PPK Asset Repair") is separated by a hyphen. Some pages used a middle dot (·) or a bullet (•) for the same
 * job; one separator on every page reads as one design. Nothing under resources/ may contain either character.
 */
class NoDotSeparatorsTest extends TestCase
{
    public function test_no_view_or_script_separates_text_with_a_dot(): void
    {
        $offenders = [];

        foreach ([resource_path('views'), resource_path('js')] as $dir) {
            foreach (File::allFiles($dir) as $file) {
                foreach (preg_split('/\R/u', $file->getContents()) as $n => $line) {
                    if (preg_match('/[·•]/u', $line)) {
                        $offenders[] = $file->getRelativePathname() . ':' . ($n + 1) . ' ' . trim($line);
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'a dot used as a separator: write " - "');
    }

    public function test_the_evaluate_page_and_a_persons_page_use_the_hyphen(): void
    {
        $source = file_get_contents(resource_path('views/maintenance/rating/evaluate.blade.php'));

        $this->assertStringContainsString('วันหลังปิดงาน - งานที่ใกล้หมดเวลาอยู่บนสุด', $source);
    }
}
