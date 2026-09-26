<?php

namespace App\Support;

/**
 * A hand-drawn signature (the data: URI the signature canvas hands over) made ready for a printed report's "ลงชื่อ" line.
 *
 * The canvas exports its whole area, nearly all of it empty, so the strokes float somewhere above the line they are meant to sit
 * on. The empty margin is trimmed away and the picture sized to the room the field has.
 */
final class ReportSignature
{
    /** Room the field has, in CSS px (the unit of an <img> width / height). */
    public const MAX_WIDTH = 220;
    public const MAX_HEIGHT = 50;

    /** No canvas (even at 4x pixel density) is bigger: a header claiming more is not a signature. */
    private const MAX_SOURCE_PX = 4000;

    /**
     * @return array{src:string, width:int, height:int}|null  null → leave the line blank to sign by hand: the data is not an
     *                                                        image GD can read, or is bigger than any canvas could be
     */
    public static function fromDataUri(string $dataUri): ?array
    {
        $raw = base64_decode(substr((string) strstr($dataUri, ','), 1), true);
        $size = $raw === false ? false : @getimagesizefromstring($raw);

        // the size is read from the header first: decoding a small file that claims a huge picture would exhaust memory
        if ($size === false || $size[0] > self::MAX_SOURCE_PX || $size[1] > self::MAX_SOURCE_PX) {
            return null;
        }

        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return null;
        }

        // Crop to whatever is not the canvas's fully transparent background. (IMG_CROP_TRANSPARENT would not do: it only knows a
        // palette's transparent colour, not an alpha channel, and leaves this picture as it was.) A blank canvas would be cropped
        // away entirely → false, and the picture stays as it is.
        $background = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $trimmed = imagecropauto($image, IMG_CROP_THRESHOLD, 0.01, $background);
        if ($trimmed !== false) {
            $image = $trimmed;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height, 1);

        imagesavealpha($image, true);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();

        return [
            'src'    => 'data:image/png;base64,' . base64_encode($png),
            'width'  => max(1, (int) round($width * $scale)),
            'height' => max(1, (int) round($height * $scale)),
        ];
    }
}
