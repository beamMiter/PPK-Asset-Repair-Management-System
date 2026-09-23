<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>SLA Performance Report</title>
    <style>
        /*
         * One A4 page for a normal report: the fixed parts (header, overview, the two summary tables, the signature) use about
         * half of it, the rest is room for the "breached tickets" list. That list is never cut (the report is the full record), so
         * a long one runs on to a second page — its header row repeats there and the signature block is kept whole.
         */
        @page {
            margin: 10mm 13mm 10mm 13mm;
        }

        @font-face {
            font-family: 'sarabun';
            font-style: normal;
            font-weight: normal;
            src: url("{{ public_path('images/fonts/Sarabun-Regular.ttf') }}") format('truetype');
        }

        @font-face {
            font-family: 'sarabun';
            font-style: normal;
            font-weight: bold;
            src: url("{{ public_path('images/fonts/Sarabun-Bold.ttf') }}") format('truetype');
        }

        body {
            margin: 0;
            font-family: 'sarabun', sans-serif;
            font-size: 11pt;
            line-height: 1.25;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .en {
            font-size: 9pt;
            font-weight: normal;
            color: #64748b;
        }

        /* ===== header ===== */
        .head {
            border-bottom: 2px solid #000;
        }

        .head td {
            vertical-align: middle;
            padding: 0 0 4px 0;
        }

        .head-side {
            width: 52px;
        }

        .logo {
            width: 44px;
        }

        .head-text {
            text-align: center;
        }

        .hospital-name {
            font-size: 16pt;
            font-weight: bold;
        }

        .report-title {
            font-size: 12.5pt;
            font-weight: bold;
        }

        .meta td {
            padding: 3px 0 0 0;
            font-size: 10pt;
        }

        /* ===== sections ===== */
        .section-title {
            font-size: 11.5pt;
            font-weight: bold;
            margin: 8px 0 4px 0;
            border-left: 4px solid #1e40af;
            padding-left: 8px;
            color: #1e40af;
        }

        .section-title.danger {
            color: #ef4444;
            border-left-color: #ef4444;
        }

        /* the numbers: four timing figures, then how the period's jobs split by status (same card, coloured by status) */
        .metrics {
            margin-bottom: 3px;
        }

        .metric-card {
            width: 25%;
            padding: 2px 4px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .metric-value {
            font-size: 15pt;
            font-weight: bold;
            color: #1e40af;
            display: block;
            line-height: 1.1;
        }

        .metric-label {
            font-size: 9pt;
            color: #64748b;
            display: block;
        }

        .pair {
            table-layout: fixed;
        }

        .pair td {
            vertical-align: top;
            padding: 0;
        }

        .data th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 1px 6px;
            text-align: left;
            font-size: 9.5pt;
            font-weight: bold;
        }

        .data td {
            border: 1px solid #cbd5e1;
            padding: 0 6px;
            text-align: left;
            font-size: 9.5pt;
        }

        .data .num {
            width: 50px;
            text-align: center;
        }

        /*
         * The ticket list is the one table that can run long. Thai has no spaces to wrap at, so a value wider than its column
         * would print over the next one: the widths below come from the real values (the longest department name is ~112pt, a date
         * ~75pt at 9.5pt in this font), and anything longer than its column breaks at the column edge instead of colliding.
         */
        .tickets {
            table-layout: fixed;
        }

        .tickets thead {
            display: table-header-group;
        }

        .tickets tr {
            page-break-inside: avoid;
        }

        .tickets th,
        .tickets td {
            font-size: 9pt;
            padding: 0 4px;
            overflow-wrap: anywhere;
        }

        .muted {
            text-align: center;
            color: #64748b;
            font-size: 10pt;
            margin: 2px 0 0 0;
        }

        /* ===== signature: "ลงชื่อ [signature on the line] ผู้ส่งรายงาน", name and date centred under the line ===== */
        .sign-wrap {
            margin-top: 10px;
            page-break-inside: avoid;
        }

        .sign-wrap > tbody > tr > td {
            padding: 0;
            vertical-align: top;
        }

        .sign {
            table-layout: fixed;
            font-size: 11pt;
        }

        .sign td {
            padding: 0;
            vertical-align: bottom;
            white-space: nowrap;
        }

        .sign .tail {
            text-align: right;
        }

        .sign .line {
            height: 40px;
            text-align: center;
            border-bottom: 1px dotted #000;
        }

        /* as a block the picture has no line box under it: inline, it sits on the text baseline with the line's descender room below, which floated the ink above the line */
        .sign .line img {
            display: block;
            margin: 0 auto;
        }

        .sign .under {
            text-align: center;
            padding-top: 4px;
        }
    </style>
</head>

<body>
    @php
        // Thai month names and the Buddhist year, as the SLA page shows its dates (the app's own locale is en, so Carbon would print
        // "24 September 2026" in a Thai document)
        $thaiDate = fn ($d) => $d->copy()->locale('th')->translatedFormat('j F') . ' ' . ($d->year + 543);
        $thaiDateTime = fn ($d) => $d->format('d/m/') . ($d->year + 543) . $d->format(' H:i');
    @endphp

    <table class="head">
        <tr>
            <td class="head-side">
                @if (file_exists($hospital['logo']))
                    <img src="{{ $hospital['logo'] }}" class="logo">
                @endif
            </td>
            <td class="head-text">
                <div class="hospital-name">{{ $hospital['name_th'] }}</div>
                <div class="report-title">สรุปรายงานผลการดำเนินการตาม SLA <span class="en">(SLA Performance Summary)</span></div>
            </td>
            <td class="head-side"></td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>ช่วงข้อมูล: {{ $thaiDate($periodStart) }} – {{ $thaiDate($periodEnd) }}</td>
            <td style="text-align: right;">วันที่ออกรายงาน: {{ $thaiDate($reportDate) }}</td>
        </tr>
    </table>

    @php
        $statusColor = ['ทำตาม SLA' => '#10b981', 'เกินเวลา' => '#ef4444', 'มีความเสี่ยง' => '#f59e0b', 'ตามกำหนด' => '#1e40af'];
        // [[department, count], ...] in two columns: eight rows stacked cost as much as the whole ticket list's first half
        $deptRows = array_map(null, $chartData['department']['labels'], $chartData['department']['data']);
        $deptColumns = array_chunk($deptRows, max(4, (int) ceil(count($deptRows) / 2)));
    @endphp

    <div class="section-title">สรุปภาพรวม <span class="en">Overview</span></div>
    <table class="metrics">
        <tr>
            <td class="metric-card">
                <span class="metric-value">{{ $dashboard['avg_response_hours'] }}</span>
                <span class="metric-label">เวลาตอบกลับเฉลี่ย (ชม.)</span>
            </td>
            <td class="metric-card">
                <span class="metric-value">{{ $dashboard['avg_acceptance_hours'] }}</span>
                <span class="metric-label">เวลารับงานเฉลี่ย (ชม.)</span>
            </td>
            <td class="metric-card">
                <span class="metric-value">{{ $dashboard['avg_resolution_hours'] }}</span>
                <span class="metric-label">เวลาแก้ไขเฉลี่ย (ชม.)</span>
            </td>
            <td class="metric-card">
                <span class="metric-value">{{ $dashboard['compliance_rate'] }}%</span>
                <span class="metric-label">อัตราบรรลุ SLA</span>
            </td>
        </tr>
    </table>
    <table class="metrics">
        <tr>
            @foreach ($chartData['distribution']['labels'] as $index => $label)
                <td class="metric-card">
                    <span class="metric-value" style="color: {{ $statusColor[$label] ?? '#1e40af' }};">{{ $chartData['distribution']['data'][$index] }}</span>
                    <span class="metric-label">งาน{{ $label }} (รายการ)</span>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="section-title">งานเกินเวลาแยกตามแผนก <span class="en">Breaches by Department</span></div>
    @if (count($deptRows) > 0)
        <table class="pair">
            <tr>
                @foreach ([0, 1] as $col)
                    <td style="width: 49%;">
                        @if (isset($deptColumns[$col]))
                            <table class="data">
                                <thead>
                                    <tr>
                                        <th>แผนก</th>
                                        <th class="num">จำนวน</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($deptColumns[$col] as [$dept, $count])
                                        <tr>
                                            <td>{{ $dept }}</td>
                                            <td class="num">{{ $count }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </td>
                    @if ($col === 0)
                        <td style="width: 2%;"></td>
                    @endif
                @endforeach
            </tr>
        </table>
    @else
        <p class="muted">ไม่มีงานที่เกินเวลาในช่วงข้อมูลนี้</p>
    @endif

    {{-- a live snapshot (every open job that is late right now), not limited to the period above --}}
    <div class="section-title danger">รายการงานที่เกินเวลา ณ วันที่ออกรายงาน
        <span class="en">Breached Tickets · {{ count($breachedTickets) }} รายการ</span>
    </div>
    @if (count($breachedTickets) > 0)
        <table class="data tickets">
            <thead>
                <tr>
                    {{-- fixed layout takes its widths from this row (dompdf ignores <col>) --}}
                    <th style="width: 10.5%;">เลขที่</th>
                    <th style="width: 35%;">รายการ</th>
                    <th style="width: 22%;">แผนก</th>
                    <th style="width: 15%;">สถานะปัจจุบัน</th>
                    <th style="width: 17.5%;">กำหนดเสร็จ</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($breachedTickets as $ticket)
                    <tr>
                        <td>{{ $ticket->request_no }}</td>
                        <td>{{ $ticket->title }}</td>
                        <td>{{ $ticket->department?->name_th ?? ($ticket->department?->name_en ?? '-') }}</td>
                        <td>{{ $ticket->statusLabel() }}</td>
                        <td>{{ $thaiDateTime($ticket->sla_due_date) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">ไม่มีงานที่เกินเวลา ณ วันที่ออกรายงาน</p>
    @endif

    <table class="sign-wrap">
        <tr>
            <td style="width: 46%;"></td>
            <td>
                <table class="sign">
                    <tr>
                        {{-- fixed layout takes its widths from this row: inline, and in % (a class width or a px width is ignored) --}}
                        <td class="lead" style="width: 10%;">ลงชื่อ</td>
                        <td class="line">
                            @if (! empty($signature))
                                <img src="{{ $signature['src'] }}" width="{{ $signature['width'] }}"
                                    height="{{ $signature['height'] }}">
                            @endif
                        </td>
                        <td class="tail" style="width: 19%;">ผู้ส่งรายงาน</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="under">( ........................................ )</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="under">วันที่ ....../....../..........</td>
                        <td></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>

</html>
