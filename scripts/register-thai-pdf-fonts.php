<?php
/**
 * Registers the fonts scripts/build-thai-pdf-fonts.py built with dompdf, under fixed names — so a PDF finds them by family name
 * (`font-family: 'sarabunpdf'`) with nothing written at render time, and nothing machine-specific (dompdf's own @font-face registration
 * names its copy after a hash of the path and can leave an absolute path in installed-fonts.json).
 *
 * For each of sarabunpdf_normal.ttf / sarabunpdf_bold.ttf in public/images/fonts: writes the .ufm metrics file dompdf reads, and puts
 * the family in installed-fonts.json.
 *
 * Run (from the project root):  php scripts/register-thai-pdf-fonts.php
 */

require __DIR__ . '/../vendor/autoload.php';

$dir = __DIR__ . '/../public/images/fonts';
$registry = $dir . '/installed-fonts.json';

$installed = is_file($registry) ? json_decode(file_get_contents($registry), true, 512, JSON_THROW_ON_ERROR) : [];
$installed['sarabunpdf'] = [];

foreach (['normal', 'bold'] as $style) {
    $name = "sarabunpdf_$style";
    $font = FontLib\Font::load("$dir/$name.ttf");
    $font->parse();
    $font->saveAdobeFontMetrics("$dir/$name.ufm");
    $font->close();
    $installed['sarabunpdf'][$style] = $name;
    echo "registered $name\n";
}

file_put_contents($registry, json_encode($installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
