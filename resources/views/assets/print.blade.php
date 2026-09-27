{{-- resources/views/assets/print.blade.php --}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>Asset Sheet {{ $asset->asset_code }} - {{ config('app.title_suffix') }}</title>

    <style>
        /* Sarabun comes from the family registered in public/images/fonts/installed-fonts.json (config/dompdf.php):
           no @font-face here — the ones that used to be, pointed at public/fonts, which no longer exists. */

        html, body, * {
            font-family: "Sarabun", DejaVu Sans, sans-serif;
            box-sizing: border-box;
        }

        body {
            font-size: 12px;
            margin: 16px 20px;
            color: #000;
        }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-left   { text-align: left; }

        /* กันโดนตัดกลาง section */
        .section-block {
            page-break-inside: avoid;
            margin-bottom: 6px;
        }

        /* ====== HEADER (โทนเดียวกับใบงานซ่อม) ====== */
        .header-wrapper {
            width: 100%;
            border-bottom: 1px solid #0f4c81;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .header-logo {
            width: 14%;
        }

        .header-logo img {
            max-width: 50px;
            max-height: 50px;
            object-fit: contain;
        }

        .header-center {
            width: 56%;
            text-align: left;
            padding-left: 4px;
        }

        .header-right {
            width: 30%;
            font-size: 10px;
            text-align: right;
            line-height: 1.4;
        }

        .h-doc-title {
            font-size: 11px;
            font-weight: bold;
            margin-top: 4px;
        }

        /* ====== SECTION TITLE ====== */
        .section-title {
            font-weight: bold;
            font-size: 12px;
            margin-top: 8px;
            margin-bottom: 2px;
            padding: 3px 5px;
            border-radius: 2px;
            background: #e6f0fb;
            border-left: 3px solid #0f4c81;
        }

        .section-sub {
            font-size: 10px;
            color: #555;
            margin-top: 1px;
            margin-left: 2px;
        }

        /* ====== META TABLE ====== */
        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        table.meta td {
            padding: 2px 4px;
            vertical-align: top;
        }

        table.meta td.label {
            width: 22%;
            font-weight: bold;
            white-space: nowrap;
        }

        /* ====== GRID TABLE (SECTION 3) ====== */
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        table.grid th,
        table.grid td {
            border: 1px solid #999;
            padding: 3px 4px;
            font-size: 11px;
        }

        table.grid th {
            background: #eef3fb;
            font-weight: bold;
            text-align: center;
        }

        .footer-note {
            margin-top: 10px;
            font-size: 10px;
            color: #555;
        }

        /* ====== SIGNATURE ====== */
        .signature-line {
            margin-top: 18px;
        }

        .sig-col {
            width: 50%;
        }

        .sig-label {
            font-size: 11px;
            margin-bottom: 30px;
        }

        .sig-underline {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 160px;
        }
    </style>
</head>
<body>
@php
    // helper: ถ้า null/ว่าง → คืนค่าว่าง
    $f  = fn($v) => ($v === null || $v === '') ? '' : $v;
    $fd = fn($v) => $v ? $v->format('d/m/Y') : '';

    $statusMap = [
        'active'    => 'ใช้งานปกติ',
        'in_repair' => 'อยู่ระหว่างซ่อม',
        'disposed'  => 'จำหน่ายออกจากระบบ',
    ];
    $statusLabel = $asset->status ? ($statusMap[$asset->status] ?? '') : '';

    $dept      = $asset->department ?? null;
    $deptName  = $dept?->display_name
        ?? $dept?->name_th
        ?? $dept?->name_en
        ?? '';

    $categoryName = $asset->categoryRef->name ?? '';
    $brandModel   = trim(($asset->brand ?? '').' '.($asset->model ?? ''));

    $hospitalNameTh = 'โรงพยาบาลพระปกเกล้า';
    $hospitalNameEn = 'PHRAPOKKLAO HOSPITAL';

    $logoPath = public_path('images/logoppk.png');

    // ใช้ concept เดียวกับหน้า show
    $reqCount = $asset->maintenance_requests_count ?? 0;
    $attCount = $asset->attachments_count ?? 0;
@endphp

{{-- ================= HEADER ================= --}}
<div class="header-wrapper section-block">
    <table class="header-table">
        <tr>
            {{-- LOGO --}}
            <td class="header-logo">
                @if(file_exists($logoPath))
                    <img src="{{ $logoPath }}" alt="Logo">
                @endif
            </td>

            {{-- HOSPITAL NAME + DOC TITLE --}}
            <td class="header-center">
                <div style="font-size:11px; font-weight:bold;">
                    {{ $hospitalNameTh }}&nbsp;&nbsp;{{ $hospitalNameEn }}
                </div>
                <div class="h-doc-title">
                    แบบฟอร์มข้อมูลครุภัณฑ์ / Asset Information Sheet
                </div>
            </td>

            {{-- RIGHT META --}}
            <td class="header-right">
                <div>รหัสครุภัณฑ์: <strong>{{ $asset->asset_code ?? $asset->id }}</strong></div>
                <div>พิมพ์เมื่อ: {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ================= SECTION 1 ================= --}}
<div class="section-block">
    <div class="section-title">ส่วนที่ 1 : ข้อมูลครุภัณฑ์และการจัดหมวดหมู่</div>
    <div class="section-sub">
        รหัสครุภัณฑ์ ชื่อ หมวดหมู่ หน่วยงาน และข้อมูลจำเพาะหลักของครุภัณฑ์
    </div>

    <table class="meta">
        <tr>
            <td class="label">รหัสครุภัณฑ์</td>
            <td>
                {{ $f($asset->asset_code) }}
                @if($asset->his_asset_id)
                    <div style="font-size: 10px; color: #0f4c81;">(เลข รพจ: {{ $asset->his_asset_id }})</div>
                @endif
            </td>
            <td class="label">หมวดหมู่</td>
            <td>{{ $f($categoryName) }}</td>
        </tr>
        <tr>
            <td class="label">ชื่อครุภัณฑ์</td>
            <td>{{ $f($asset->name) }}</td>
            <td class="label">ประเภท (Type)</td>
            <td>{{ $f($asset->type) }}</td>
        </tr>
        <tr>
            <td class="label">ยี่ห้อ / รุ่น</td>
            <td>{{ $f($brandModel) }}</td>
            <td class="label">Serial</td>
            <td>{{ $f($asset->serial_number) }}</td>
        </tr>
        <tr>
            <td class="label">หน่วยงานเจ้าของ</td>
            <td>{{ $f($deptName) }}</td>
            <td class="label">สถานที่ใช้งาน</td>
            <td>{{ $f($asset->location) }}</td>
        </tr>
        <tr>
            <td class="label">สถานะปัจจุบัน</td>
            <td>{{ $f($statusLabel) }}</td>
            <td class="label"></td>
            <td></td>
        </tr>
    </table>
</div>

{{-- ================= SECTION 2 ================= --}}
<div class="section-block">
    <div class="section-title">ส่วนที่ 2 : ข้อมูลการจัดซื้อและภาพรวมการใช้งาน</div>
    <div class="section-sub">
        ข้อมูลวันที่จัดซื้อ ระยะเวลารับประกัน และจำนวนคำขอซ่อม/ไฟล์แนบที่เกี่ยวข้อง
    </div>

    <table class="meta">
        <tr>
            <td class="label">วันที่จัดซื้อ</td>
            <td>{{ $fd($asset->purchase_date) }}</td>
            <td class="label">วันหมดประกัน</td>
            <td>{{ $fd($asset->warranty_expire) }}</td>
        </tr>
        <tr>
            <td class="label">จำนวนคำขอซ่อม</td>
            <td>{{ $reqCount }} งาน</td>
            <td class="label">ไฟล์แนบของครุภัณฑ์</td>
            <td>{{ $attCount }} ไฟล์</td>
        </tr>
    </table>
</div>

{{-- ================= SECTION 3 ================= --}}
<div class="section-block">
    <div class="section-title">ส่วนที่ 3 : ประวัติงานซ่อม (เติมข้อมูลเอง / แนบเพิ่ม)</div>
    <div class="section-sub">
        สำหรับบันทึกใบงานซ่อมหลัก ๆ ที่เกี่ยวข้องกับครุภัณฑ์นี้
    </div>

    <table class="grid">
        <thead>
        <tr>
            <th style="width: 8%;">ลำดับ</th>
            <th style="width: 20%;">เลขที่ใบงาน</th>
            <th style="width: 18%;">วันที่แจ้ง</th>
            <th>อาการ / รายละเอียดปัญหา</th>
            <th style="width: 16%;">สถานะ</th>
        </tr>
        </thead>
        <tbody>
        {{-- เว้นแถวไว้กรอกเอง ถ้าอนาคตอยากดึง log จริงค่อย loop --}}
        @for($i = 1; $i <= 4; $i++)
            <tr>
                <td class="text-center">&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        @endfor
        </tbody>
    </table>
</div>

{{-- ================= SECTION 4 ================= --}}
<div class="section-block">
    <div class="section-title">ส่วนที่ 4 : หมายเหตุและการรับรองข้อมูล</div>
    <div class="section-sub">
        สำหรับผู้ดูแลครุภัณฑ์และผู้บังคับบัญชาลงนามรับรองข้อมูล
    </div>

    <table class="meta signature-line">
        <tr>
            <td class="sig-col text-center">
                <div class="sig-label">ผู้ดูแลครุภัณฑ์</div>
                <div class="sig-underline">&nbsp;</div>
            </td>
            <td class="sig-col text-center">
                <div class="sig-label">ผู้ตรวจสอบ / หัวหน้าหน่วยงาน</div>
                <div class="sig-underline">&nbsp;</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        หมายเหตุ: ฟอร์มนี้จัดทำจากระบบ Asset Repair Management ของโรงพยาบาล
        เพื่อใช้เป็นเอกสารประกอบการบริหารจัดการครุภัณฑ์
    </div>
</div>

</body>
</html>
