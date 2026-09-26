<?php

namespace Tests\Feature;

use App\Support\ReportSignature;
use Tests\TestCase;

/**
 * The signature canvas exports its whole area with the pen strokes somewhere in it, and the report printed the picture as it came:
 * the ink floated a hand's breadth above the "ลงชื่อ" line. It is now trimmed to the strokes and sized to the field.
 */
class ReportSignatureTest extends TestCase
{
    /** A canvas-like PNG data URI: transparent, with one stroke inside the box the arguments give. */
    private function canvasWithInk(int $canvasW, int $canvasH, int $x1, int $y1, int $x2, int $y2): string
    {
        $im = imagecreatetruecolor($canvasW, $canvasH);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledrectangle($im, $x1, $y1, $x2, $y2, imagecolorallocatealpha($im, 15, 45, 92, 0));

        ob_start();
        imagepng($im);

        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }

    private function decodedSize(array $signature): array
    {
        $image = imagecreatefromstring(base64_decode(substr($signature['src'], strlen('data:image/png;base64,'))));

        return [imagesx($image), imagesy($image)];
    }

    public function test_the_empty_margin_around_the_strokes_is_trimmed_away(): void
    {
        // strokes occupy 600 x 200 px in the middle of a 928 x 400 canvas
        $signature = ReportSignature::fromDataUri($this->canvasWithInk(928, 400, 150, 100, 749, 299));

        $this->assertSame([600, 200], $this->decodedSize($signature), 'the picture itself is cut to the ink');
        // the ratio shown is the ink's (3:1), not the canvas's (2.3:1): what sat on the line is the strokes, not the empty area
        $this->assertEqualsWithDelta(3.0, $signature['width'] / $signature['height'], 0.05);
    }

    public function test_it_is_sized_to_the_room_the_signature_field_has(): void
    {
        $tall = ReportSignature::fromDataUri($this->canvasWithInk(928, 400, 300, 20, 500, 380));
        $this->assertSame(ReportSignature::MAX_HEIGHT, $tall['height'], 'a tall signature is limited by the height of the field');
        $this->assertLessThanOrEqual(ReportSignature::MAX_WIDTH, $tall['width']);

        $wide = ReportSignature::fromDataUri($this->canvasWithInk(928, 400, 10, 150, 900, 200));
        $this->assertSame(ReportSignature::MAX_WIDTH, $wide['width'], 'a wide one by its width');
        $this->assertLessThanOrEqual(ReportSignature::MAX_HEIGHT, $wide['height']);
    }

    public function test_a_small_signature_is_not_blown_up(): void
    {
        $signature = ReportSignature::fromDataUri($this->canvasWithInk(928, 400, 100, 100, 159, 129));   // 60 x 30

        $this->assertSame(60, $signature['width']);
        $this->assertSame(30, $signature['height']);
    }

    public function test_a_blank_canvas_is_kept_as_it_is_rather_than_trimmed_to_nothing(): void
    {
        $im = imagecreatetruecolor(200, 100);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        ob_start();
        imagepng($im);
        $blank = 'data:image/png;base64,' . base64_encode(ob_get_clean());

        $signature = ReportSignature::fromDataUri($blank);

        $this->assertNotNull($signature);
        $this->assertSame([200, 100], $this->decodedSize($signature));
    }

    public function test_something_that_is_not_an_image_leaves_the_line_blank(): void
    {
        $this->assertNull(ReportSignature::fromDataUri('data:image/png;base64,' . base64_encode('not a picture')));
        $this->assertNull(ReportSignature::fromDataUri('data:image/png;base64,@@@ not base64 @@@'));
        $this->assertNull(ReportSignature::fromDataUri('data:image/png'));
    }

    /** A few bytes that claim a 5000 x 5000 picture: decoding it would take 100 MB, so its header is enough to turn it down. */
    public function test_a_picture_bigger_than_any_canvas_is_turned_down_before_it_is_decoded(): void
    {
        $ihdr = pack('NNCCCCC', 5000, 5000, 8, 6, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));

        $this->assertNull(ReportSignature::fromDataUri('data:image/png;base64,' . base64_encode($png)));
    }
}
