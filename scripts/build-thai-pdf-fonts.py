#!/usr/bin/env python3
"""
Builds the Thai font the PDF reports use: Sarabun plus a set of ready-made "tone mark over an upper vowel" glyphs.

Why: Thai stacks a tone mark (่ ้ ๊ ๋) on top of an upper vowel (ั ิ ี ึ ื), and puts a tone mark over the nikhahit of ำ. A browser does
that through the font's GSUB/GPOS tables (a smaller tone-mark glyph, moved up and along). dompdf reads neither table: it draws each
character's own glyph at the default position, and there the tone mark sits inside the vowel and cannot be seen — "ที่" prints as "ที",
"ทั้งหมด" as "ทังหมด", "เฉลี่ย" as "เฉลีย". So the layout is left to HarfBuzz once, here, and its result is baked into new glyphs:

    consonant + vowel + tone mark   ->   consonant + ONE private-use character whose glyph is the vowel and the tone mark,
                                         already substituted and positioned exactly as HarfBuzz places them after that consonant

App\\Support\\ThaiPdfText does the replacement in the HTML before it goes to dompdf (it reads resources/data/thai-pdf-font.json, which
this script writes: the same numbering on both sides). Covered: upper vowel + tone (every consonant), tone + ำ (every consonant), and
a lone upper vowel or tone mark over ป ฝ ฟ (whose ascender the font moves them away from).

Needs:  pip install fonttools uharfbuzz
Run:    python3 scripts/build-thai-pdf-fonts.py            (from the project root)
Output: public/images/fonts/sarabunpdf_normal.ttf, sarabunpdf_bold.ttf (+ their .ufm and installed-fonts.json entry, by
        scripts/register-thai-pdf-fonts.php, which this runs at the end) and resources/data/thai-pdf-font.json
The source fonts are public/images/fonts/Sarabun-{Regular,Bold}.ttf (SIL Open Font License 1.1; the derived fonts keep its notices).
"""
import json
import subprocess
import sys
from pathlib import Path

import uharfbuzz as hb
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.ttGlyphPen import TTGlyphPen
from fontTools.ttLib import TTFont

ROOT = Path(__file__).resolve().parent.parent
FONTS = ROOT / 'public' / 'images' / 'fonts'
MANIFEST = ROOT / 'resources' / 'data' / 'thai-pdf-font.json'

CONSONANTS = ''.join(chr(c) for c in range(0x0E01, 0x0E2F))    # ก .. ฮ
UPPER = ['ั', 'ิ', 'ี', 'ึ', 'ื']     # ั ิ ี ึ ื
TONES = ['่', '้', '๊', '๋']              # ่ ้ ๊ ๋
SARA_AM = 'ำ'
ASCENDERS = 'ปฝฟ'

# The clusters that follow a consonant; a cluster's position in this list, and the consonant's in CONSONANTS, number its glyph.
CLUSTERS = (
    [v + t for v in UPPER for t in TONES]      # 20: vowel + tone
    + [t + SARA_AM for t in TONES]             # 4:  tone + ำ
    + list(UPPER)                              # 5:  a lone upper vowel   (used only over ASCENDERS)
    + list(TONES)                              # 4:  a lone tone mark     (used only over ASCENDERS)
)
LONE = set(UPPER) | set(TONES)

BASE = 0xE000      # the private-use area; Sarabun itself uses only U+F8FF
STRIDE = 40        # >= len(CLUSTERS)
QUANTUM = 10       # font units: offsets closer than this share one glyph (a hundredth of an em is not visible)


def pua(consonant_index, cluster_index):
    return BASE + consonant_index * STRIDE + cluster_index


def build(weight):
    src = FONTS / f'Sarabun-{weight}.ttf'
    out = FONTS / f'sarabunpdf_{"normal" if weight == "Regular" else "bold"}.ttf'   # the names dompdf's installed-fonts.json holds
    tt = TTFont(src)
    cmap = tt.getBestCmap()
    order = tt.getGlyphOrder()
    glyph_set = tt.getGlyphSet()
    hb_font = hb.Font(hb.Face(hb.Blob.from_file_path(str(src))))

    signatures = {}          # signature -> glyph name
    glyphs = {}              # glyph name -> (glyph, advance)
    cmap_add = {}            # private-use codepoint -> glyph name
    skipped = []

    for ci, consonant in enumerate(CONSONANTS):
        for ki, cluster in enumerate(CLUSTERS):
            if cluster in LONE and consonant not in ASCENDERS:
                continue
            text = consonant + cluster
            buf = hb.Buffer()
            buf.add_str(text)
            buf.guess_segment_properties()
            hb.shape(hb_font, buf, {})
            names = [order[i.codepoint] for i in buf.glyph_infos]
            pos = buf.glyph_positions

            if names[0] != cmap[ord(consonant)] or len(names) < 2:
                skipped.append(text)      # the font changes the consonant itself: not something a mark-only glyph can carry
                continue

            marks = [(names[i], pos[i].x_advance, pos[i].x_offset, pos[i].y_offset) for i in range(1, len(names))]
            signature = tuple((n, adv, round(dx / QUANTUM), round(dy / QUANTUM)) for n, adv, dx, dy in marks)

            if signature not in signatures:
                name = f'thpdf.{len(signatures)}'
                signatures[signature] = name
                pen = TTGlyphPen(glyph_set)
                x = 0
                for n, adv, dx, dy in marks:
                    glyph_set[n].draw(TransformPen(pen, (1, 0, 0, 1, x + dx, dy)))
                    x += adv
                glyphs[name] = (pen.glyph(), x)
            cmap_add[pua(ci, ki)] = signatures[signature]

    # add the glyphs
    glyf = tt['glyf']
    hmtx = tt['hmtx']
    new_order = list(order)
    for name, (glyph, advance) in glyphs.items():
        glyph.recalcBounds(glyf)
        glyf[name] = glyph
        hmtx[name] = (advance, getattr(glyph, 'xMin', 0) if glyph.numberOfContours else 0)
        new_order.append(name)
    tt.setGlyphOrder(new_order)
    tt['maxp'].numGlyphs = len(new_order)
    for table in tt['cmap'].tables:
        if table.isUnicode():
            table.cmap.update(cmap_add)

    # its own name: a font that is not Sarabun, so nothing mistakes it for the original
    for record in tt['name'].names:
        if record.nameID in (1, 16):
            record.string = 'Sarabun PDF'
        elif record.nameID == 4:
            record.string = f'Sarabun PDF {weight}'
        elif record.nameID == 6:
            record.string = f'SarabunPDF-{weight}'
        elif record.nameID == 3:
            record.string = f'SarabunPDF-{weight};tone marks composed for dompdf'

    tt.save(out)
    print(f'{out.name}: {len(glyphs)} new glyphs for {len(cmap_add)} private-use characters'
          + (f'; {len(skipped)} clusters skipped (consonant changed): {skipped[:6]}...' if skipped else ''))
    return len(glyphs)


def main():
    for weight in ('Regular', 'Bold'):
        build(weight)

    MANIFEST.parent.mkdir(parents=True, exist_ok=True)
    MANIFEST.write_text(json.dumps({
        'note': 'Written by scripts/build-thai-pdf-fonts.py — the numbering App\\Support\\ThaiPdfText uses.',
        'base': BASE,
        'stride': STRIDE,
        'consonants': CONSONANTS,
        'ascenders': ASCENDERS,
        'clusters': CLUSTERS,
    }, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(f'wrote {MANIFEST.relative_to(ROOT)}')

    # dompdf reads a font's metrics from a .ufm beside it, and finds the family by name in installed-fonts.json
    subprocess.run(['php', str(ROOT / 'scripts' / 'register-thai-pdf-fonts.php')], check=True, cwd=ROOT)


if __name__ == '__main__':
    sys.exit(main())
