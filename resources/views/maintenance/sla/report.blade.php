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

        /*
         * The font is 'sarabunpdf' — Sarabun with the tone-mark-over-vowel glyphs built in (scripts/build-thai-pdf-fonts.py), installed in
         * public/images/fonts/installed-fonts.json, so no @font-face. dompdf cannot place a tone mark on a vowel itself ("ที่" printed
         * as "ที"): the controller swaps those clusters for characters only this font has (App\Support\ThaiPdfText), so the text has to
         * be in this font.
         */
        body {
            margin: 0;
            font-family: 'sarabunpdf', sans-serif;
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

        .note-box {
            margin-top: 8px;
            border: 1px solid #cbd5e1;
            padding: 3px 8px;
            font-size: 10pt;
        }

        .note-box .label {
            font-weight: bold;
            color: #1e40af;
        }

        .subtitle {
            margin: -2px 0 3px 0;
            font-size: 9pt;
            color: #64748b;
        }

        .subtitle.partial {
            color: #b45309;
        }

        /* how the figures are worked out: what a reader (or an auditor) needs to take the numbers at face value */
        .method {
            margin-top: 8px;
            font-size: 8pt;
            line-height: 1.2;
            color: #64748b;
        }

        /* ===== signatures: two blocks, "ลงชื่อ [line]" with the name, the role and the date centred under the line ===== */
        .sign-wrap {
            margin-top: 12px;
            page-break-inside: avoid;
        }

        .sign-wrap > tbody > tr > td {
            padding: 0 8px;
            vertical-align: top;
        }

        .sign {
            table-layout: fixed;
            font-size: 10.5pt;
        }

        .sign td {
            padding: 0;
            vertical-align: bottom;
            white-space: nowrap;
        }

        /* the same height with or without a picture (50 px at most): two blocks side by side must put their lines, names and dates level */
        .sign .line {
            height: 54px;
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
            padding-top: 3px;
        }

        .sign .role {
            font-weight: bold;
        }
    </style>
</head>

<body>
    @php
        // optional inputs: the report is also built without a note, a preparer or a chosen subset
        $note = $note ?? '';
        $preparedBy = $preparedBy ?? null;
        $breachedTotal = $breachedTotal ?? count($breachedTickets);
        $thaiDate = fn ($d) => \App\Support\ThaiDate::long($d);
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
            <td style="text-align: right;">วันที่ออกรายงาน: {{ \App\Support\ThaiDate::longWithTime($reportDate) }}</td>
        </tr>
        @if ($preparedBy)
            <tr>
                <td colspan="2">จัดทำโดย: {{ $preparedBy }}</td>
            </tr>
        @endif
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
            <td class="metric-card" style="width: 20%;">
                <span class="metric-value" style="color: #334155;">{{ array_sum($chartData['distribution']['data']) }}</span>
                <span class="metric-label"><b>งานทั้งหมดในช่วง</b></span>
            </td>
            @foreach ($chartData['distribution']['labels'] as $index => $label)
                <td class="metric-card" style="width: 20%;">
                    <span class="metric-value" style="color: {{ $statusColor[$label] ?? '#1e40af' }};">{{ $chartData['distribution']['data'][$index] }}</span>
                    <span class="metric-label">{{ $label }}</span>
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
                                            <td>{{ \App\Support\ThaiText::words($dept) }}</td>
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
    @php $shown = count($breachedTickets); @endphp
    <div class="section-title danger">รายการงานที่เกินเวลา ณ วันที่ออกรายงาน <span class="en">Breached Tickets</span></div>
    @if ($breachedTotal > 0)
        {{-- a list the reader chose from must say so: a cut list read as the whole picture would understate the backlog --}}
        @if ($shown < $breachedTotal)
            <div class="subtitle partial">แสดง {{ $shown }} จากงานที่เกินเวลาทั้งหมด {{ $breachedTotal }} รายการ (เลือกพิมพ์เฉพาะบางรายการ)</div>
        @else
            <div class="subtitle">ทั้งหมด {{ $breachedTotal }} รายการ เรียงจากเกินกำหนดนานที่สุด</div>
        @endif
    @endif
    @if ($shown > 0)
        <table class="data tickets">
            <thead>
                <tr>
                    {{-- fixed layout takes its widths from this row (dompdf ignores <col>) --}}
                    <th style="width: 10%;">เลขที่</th>
                    <th style="width: 29%;">รายการ</th>
                    <th style="width: 19%;">แผนก</th>
                    <th style="width: 16%;">ผู้รับผิดชอบ</th>
                    <th style="width: 14%;">สถานะ</th>
                    <th style="width: 12%;">ล่าช้า</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($breachedTickets as $ticket)
                    <tr>
                        <td>{{ $ticket->request_no }}</td>
                        <td>{{ \App\Support\ThaiText::words($ticket->title) }}</td>
                        <td>{{ \App\Support\ThaiText::words($ticket->department?->name_th ?? ($ticket->department?->name_en ?? '-')) }}</td>
                        <td>{{ \App\Support\ThaiText::words($ticket->technician?->name ?? 'ยังไม่ระบุ') }}</td>
                        <td>{{ \App\Support\ThaiText::words($ticket->statusLabel()) }}</td>
                        <td>{{ $ticket->overdueLabel($reportDate) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($breachedTotal > 0)
        <p class="muted">ไม่ได้เลือกรายการงานที่เกินเวลามาแสดง</p>
    @else
        <p class="muted">ไม่มีงานที่เกินเวลา ณ วันที่ออกรายงาน</p>
    @endif

    @if ($note !== '')
        <div class="note-box">
            <span class="label">ข้อสังเกต / ข้อเสนอแนะ:</span>
            {!! nl2br((string) \App\Support\ThaiText::words($note)) !!}
        </div>
    @endif

    <div class="method">
        วิธีคิด: อัตราบรรลุ SLA = งานที่ซ่อมเสร็จภายในกำหนด ÷ งานที่ซ่อมเสร็จทั้งหมดในช่วงข้อมูล -
        เวลาตอบกลับ = แจ้งซ่อม → รับทราบ, เวลารับงาน = รับทราบ → รับเรื่อง, เวลาแก้ไข = แจ้งซ่อม → ซ่อมเสร็จ (ไม่นับช่วงที่หยุดชั่วคราว) -
        "งานทั้งหมดในช่วง" ไม่รวมงานที่ยกเลิกและงานที่ไม่รับเรื่อง -
        รายการงานที่เกินเวลาคืองานที่ยังไม่เสร็จและเลยกำหนด ณ เวลาที่ออกรายงาน ไม่จำกัดตามช่วงข้อมูล และ "ล่าช้า" นับจากกำหนดเสร็จ
    </div>

    @php
        // the drawn signature is the preparer's: whoever prints the report signs it on screen; the approver signs the paper
        $signers = [
            ['role' => 'ผู้จัดทำรายงาน', 'name' => $preparedBy, 'signature' => $signature ?? null],
            ['role' => 'ผู้อนุมัติ', 'name' => null, 'signature' => null],
        ];
    @endphp
    <table class="sign-wrap">
        <tr>
            @foreach ($signers as $signer)
                <td style="width: 50%;">
                    <table class="sign">
                        <tr>
                            {{-- fixed layout takes its widths from this row: inline, and in % (a class width or a px width is ignored) --}}
                            <td style="width: 15%;">ลงชื่อ</td>
                            <td class="line">
                                @if (! empty($signer['signature']))
                                    <img src="{{ $signer['signature']['src'] }}" width="{{ $signer['signature']['width'] }}"
                                        height="{{ $signer['signature']['height'] }}">
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td class="under">( {{ $signer['name'] ?: '........................................' }} )</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td class="under role">{{ $signer['role'] }}</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td class="under">วันที่ ....../....../..........</td>
                        </tr>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>

</body>

</html>
