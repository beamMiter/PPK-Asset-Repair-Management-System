{{-- resources/views/maintenance/pdf/work_order.blade.php — rendered by MaintenancePrintController --}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>Maintenance Work Order #{{ $req->request_no ?? $req->id }}</title>

    <style>
        /* ====== SARABUN FONT ====== */
        @font-face {
            font-family: "Sarabun";
            font-style: normal;
            font-weight: normal;
            src: url("{{ public_path('fonts/Sarabun-Regular.ttf') }}") format("truetype");
        }

        @font-face {
            font-family: "Sarabun";
            font-style: normal;
            font-weight: bold;
            src: url("{{ public_path('fonts/Sarabun-Bold.ttf') }}") format("truetype");
        }

        html, body, * {
            font-family: "Sarabun", DejaVu Sans, sans-serif;
            box-sizing: border-box;
        }

        body {
            font-size: 12px;
            margin: 16px 20px;
            color: #000;
        }

        .text-center { text-align:center; }
        .text-right  { text-align:right; }
        .small       { font-size:10px; }

        /* กันโดนตัดกลาง section */
        .section-block { page-break-inside: avoid; }

        /* ===== HEADER ===== */
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

        .header-table td { vertical-align: top; }

        .header-logo { width: 14%; }

        .header-logo img {
            max-width: 50px;
            max-height: 50px;
        }

        .header-center {
            width: 56%;
            text-align: left;
            padding-left: 4px;
        }

        .header-right {
            width: 30%;
            text-align: right;
            font-size: 10px;
            line-height: 1.4;
        }

        .h-doc-title {
            font-size: 11px;
            font-weight: bold;
            margin-top: 4px;
        }

        /* ===== SECTION TITLE ===== */
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
            margin-bottom: 2px;
            padding: 3px 5px;
            background: #e6f0fb;
            border-left: 3px solid #0f4c81;
            border-radius: 2px;
        }

        .section-sub {
            font-size: 10px;
            color:#555;
            margin-left: 2px;
            margin-bottom: 2px;
        }

        /* ===== TABLES ===== */
        table.meta {
            width:100%;
            border-collapse:collapse;
            margin-top:3px;
        }
        table.meta td {
            padding:2px 3px;
            vertical-align:top;
        }
        table.meta td.label {
            width:22%;
            font-weight:bold;
            white-space:nowrap;
        }

        table.grid {
            width:100%;
            border-collapse:collapse;
            margin-top:4px;
        }
        table.grid th,
        table.grid td {
            border:1px solid #999;
            padding:2px 3px;
            font-size:11px;
        }
        table.grid th {
            background:#eef3fb;
        }

        /* ตารางทีมเจ้าหน้าที่ให้แน่นขึ้น */
        table.grid.grid-workers th,
        table.grid.grid-workers td {
            padding:1px 2px;
            font-size:10px;
        }

        /* ===== TWO-COL (รายละเอียด + ทีมเจ้าหน้าที่) ===== */
        table.two-col {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.two-col td {
            vertical-align: top;
            padding: 0 4px;
        }
        .two-col-left  { width: 55%; }
        .two-col-right { width: 45%; }

        /* ===== BOXES ===== */
        .box {
            border:1px solid #d1d5db;
            border-radius:3px;
            padding:5px 7px;
            margin-top:3px;
            font-size:11px;
        }
        .box-muted { background:#f9fafb; }

        /* ===== CHECKBOX LOOK ===== */
        .checkbox-box {
            display:inline-block;
            width:8px;
            height:8px;
            border:1px solid #6b7280;
            margin-right:4px;
        }
        .checkbox-box.checked { background:#111827; }

        /* ===== SIGNATURE ===== */
        .signature-table {
            width:100%;
            border-collapse:collapse;
            margin-top:10px;
            font-size:11px;
        }
        .signature-table td {
            width:50%;
            padding-top:5px;
        }
        .signature-line {
            border-bottom:1px solid #000;
            width:85%;
            height:16px;
        }
    </style>
</head>

<body>
@php
    $hospitalNameTh = $hospital['name_th'] ?? 'โรงพยาบาลพระปกเกล้า';
    $hospitalNameEn = $hospital['name_en'] ?? 'PHRAPOKKLAO HOSPITAL';
    $logoPath       = $hospital['logo'] ?? public_path('images/logoppk1.png');

    $opLog = $req->operationLog;

    $statusLabel = $req->statusLabel();
    $workers = $req->workers ?? collect();
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

            {{-- HOSPITAL NAME --}}
            <td class="header-center">
                <div style="font-size:11px; font-weight:bold;">
                    {{ $hospitalNameTh }}&nbsp;&nbsp;{{ $hospitalNameEn }}
                </div>

                <div class="h-doc-title">
                    แบบฟอร์มใบแจ้งซ่อม / Maintenance Work Order
                </div>
            </td>

            {{-- META --}}
            <td class="header-right">
                <div>เลขที่ใบงาน: <strong>{{ $req->request_no ?? $req->id }}</strong></div>
                <div>พิมพ์เมื่อ: {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ================= ส่วนที่ 1 — ข้อมูลงานซ่อมและผู้แจ้ง ================= --}}
<div class="section-block">
    <div class="section-title">ส่วนที่ 1 : ข้อมูลงานซ่อมและผู้แจ้ง</div>
    <div class="section-sub">รายละเอียดภาพรวมของใบงาน ผู้แจ้ง หน่วยงาน และเวลาเหตุการณ์หลัก</div>

    <table class="meta">
        <tr>
            <td class="label">หมายเลขงาน</td>
            <td>#{{ $req->id }}</td>
            <td class="label">สถานะปัจจุบัน</td>
            <td>{{ $statusLabel }}</td>
        </tr>
        <tr>
            <td class="label">ผู้แจ้ง</td>
            <td>
                {{ $req->reporter->name ?? $req->reporter_name ?? '-' }}<br>
                @if($req->reporter_email)
                    <span class="small">{{ $req->reporter_email }}</span><br>
                @endif
                @if($req->reporter_phone)
                    <span class="small">โทร. {{ $req->reporter_phone }}</span>
                @endif
            </td>
            <td class="label">หน่วยงาน / สถานที่ติดตั้ง</td>
            <td>
                {{ $req->location_text ?? $req->department->name_th ?? $req->department->name_en ?? '-' }}<br>
                @if($req->department?->code)
                    <span class="small">รหัสหน่วยงาน: {{ $req->department->code }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">ทรัพย์สิน</td>
            <td>
                {{ $req->asset->name ?? ($req->asset_id ? '#'.$req->asset_id : '-') }}<br>
                @if($req->asset?->asset_code)
                    <span class="small">รหัสครุภัณฑ์: {{ $req->asset->asset_code }}</span>
                @endif
            </td>
            <td class="label">เจ้าหน้าที่หลัก</td>
            <td>{{ $req->technician->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <th>รับคำขอ</th>
            <th>มอบหมายทีมเจ้าหน้าที่</th>
            <th>เสร็จสิ้น / ปิดงาน</th>
        </tr>
        <tr>
            <td class="text-center">
                {{ optional($req->request_date ?? $req->created_at)->format('d/m/Y H:i') ?? '—' }}
            </td>
            <td class="text-center">{{ optional($req->assigned_date)->format('d/m/Y H:i') ?? '—' }}</td>
            <td class="text-center">{{ optional($req->completed_date)->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>
</div>

{{-- ================= ส่วนที่ 2 — รายละเอียดปัญหา + ทีมเจ้าหน้าที่ ================= --}}
<div class="section-block">
    <table class="two-col">
        <tr>
            {{-- LEFT: รายละเอียดปัญหา --}}
            <td class="two-col-left">
                <div class="section-title">ส่วนที่ 2 : รายละเอียดปัญหาและหัวข้อใบงาน</div>

                <div class="box box-muted">
                    <div class="small" style="color:#555;">หัวข้อใบงาน</div>
                    <div style="font-weight:bold; margin-top:1px;">
                        {{ $req->title ?: '-' }}
                    </div>
                </div>

                <div class="box" style="min-height:60px;">
                    <div class="small" style="color:#555; margin-bottom:2px;">รายละเอียด / อาการเสีย</div>
                    {!! nl2br(e($req->description ?: '-')) !!}
                </div>
            </td>

            {{-- RIGHT: ทีมเจ้าหน้าที่ --}}
            <td class="two-col-right">
                <div class="section-title">ทีมเจ้าหน้าที่ที่รับผิดชอบ</div>

                @if($workers->isEmpty())
                    <div class="box box-muted small">
                        ยังไม่ได้มอบหมายทีมเจ้าหน้าที่ (เจ้าหน้าที่หลัก: {{ $req->technician->name ?? '-' }})
                    </div>
                @else
                    <table class="grid grid-workers">
                        <tr>
                            <th style="width: 12%;">ลำดับ</th>
                            <th style="width: 58%;">ชื่อเจ้าหน้าที่ / บทบาทในทีม</th>
                            <th style="width: 30%;">สถานะ</th>
                        </tr>
                        @foreach($workers as $i => $w)
                            @php
                                $assign = $req->assignments->firstWhere('user_id', $w->id);
                                $aStatus = $assign?->status;
                                $statusText = $aStatus === \App\Models\MaintenanceAssignment::STATUS_IN_PROGRESS ? 'กำลังดำเนินการ'
                                             : ($aStatus === \App\Models\MaintenanceAssignment::STATUS_DONE ? 'เสร็จสิ้น'
                                             : ($aStatus === \App\Models\MaintenanceAssignment::STATUS_CANCELLED ? 'ยกเลิกการซ่อมบำรุง' : 'ไม่ระบุ'));
                            @endphp
                            <tr>
                                <td class="text-center">{{ $i + 1 }}</td>
                                <td>
                                    {{ $w->name }}<br>
                                    @if($w->role_label || $assign?->role)
                                        <span class="small">
                                            {{ $w->role_label }}
                                            @if($assign?->role)
                                                {{ $w->role_label ? ' · ' : '' }}{{ $assign->role }}
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="small">{{ $statusText }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>

{{-- ================= ส่วนที่ 3 — รายงานการปฏิบัติงานและค่าใช้จ่าย ================= --}}
<div class="section-block">
    <div class="section-title">ส่วนที่ 3 : รายงานการปฏิบัติงานและค่าใช้จ่าย</div>
    <div class="section-sub">สรุปการปฏิบัติงาน วิธีการคิดค่าใช้จ่าย และรายละเอียดประกอบ</div>

    <table class="meta">
        <tr>
            <td class="label">วันที่ปฏิบัติงาน</td>
            <td style="width:35%;">
                {{ optional($opLog?->operation_date)->format('d/m/Y') ?? '—' }}
            </td>
            <td class="label">หน่วยงานที่เกี่ยวข้อง</td>
            <td>{{ $opLog->hospital_name ?? $hospitalNameTh }}</td>
        </tr>
    </table>

    <div class="box box-muted">
        <div class="small" style="color:#555;">วิธีการปฏิบัติ / การคิดค่าใช้จ่าย</div>
        <div>
            <span class="checkbox-box {{ ($opLog->operation_method ?? '') === 'requisition' ? 'checked' : '' }}"></span>
            ตามใบเบิกครุภัณฑ์ / วัสดุ
        </div>
        <div>
            <span class="checkbox-box {{ ($opLog->operation_method ?? '') === 'service_fee' ? 'checked' : '' }}"></span>
            ค่าบริการ / ค่าแรงเจ้าหน้าที่
        </div>
        <div>
            <span class="checkbox-box {{ ($opLog->operation_method ?? '') === 'other' ? 'checked' : '' }}"></span>
            อื่น ๆ
        </div>
    </div>

    <div class="box">
        <div class="small" style="color:#555;">ประเภทงานที่ปฏิบัติ</div>
        <div>
            <span class="checkbox-box {{ ($opLog->issue_software ?? false) ? 'checked' : '' }}"></span>
            Software
        </div>
        <div>
            <span class="checkbox-box {{ ($opLog->issue_hardware ?? false) ? 'checked' : '' }}"></span>
            Hardware
        </div>
    </div>

    <div class="box" style="min-height:40px;">
        <div class="small" style="color:#555;">หมายเหตุ / รายละเอียดประกอบ</div>
        {!! nl2br(e($opLog->remark ?? '-')) !!}
    </div>
</div>

{{-- ================= ลายเซ็น ================= --}}
<div class="section-block">
    <table class="signature-table">
        <tr>
            <td>
                <div class="small">ผู้แจ้ง</div>
                <div class="signature-line"></div>
                <div class="small">
                    ( {{ $req->reporter->name ?? '........................' }} )
                </div>
                <div class="small">วันที่ ....../....../..........</div>
            </td>
            <td>
                <div class="small">เจ้าหน้าที่ผู้ปฏิบัติงาน</div>
                <div class="signature-line"></div>
                <div class="small">
                    ( {{ $req->technician->name ?? '........................' }} )
                </div>
                <div class="small">วันที่ ....../....../..........</div>
            </td>
        </tr>
    </table>

    <div class="small" style="color:#555; margin-top:6px;">
        หมายเหตุ: เอกสารนี้จัดทำจากระบบ Maintenance Work Order ของ{{ $hospitalNameTh }} เพื่อใช้ประกอบงานซ่อมบำรุง
    </div>
</div>

</body>
</html>
