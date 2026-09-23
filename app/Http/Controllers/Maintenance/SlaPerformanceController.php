<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Support\ReportSignature;
use App\Support\ThaiDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class SlaPerformanceController extends Controller
{
    /**
     * Most rows each tab of the page's "เกินเวลา" / "ใกล้ครบกำหนด" list shows (the most overdue / the soonest due first).
     * The tab badges and the PDF report keep the full lists, so a cut list says how many it left out.
     */
    public const TICKET_LIST_LIMIT = 20;

    public function index(Request $request)
    {
        $data = $this->getSlaDashboardData($request);
        $data['ticketLimit'] = self::TICKET_LIST_LIMIT;

        return view('maintenance.sla.index', $data);
    }

    public function report(Request $request)
    {
        $data = $this->getSlaDashboardData($request);

        // signature is a data: URI from a canvas in the dashboard; only accept
        // an inline image so nothing else can be piped into the PDF's <img src>.
        $validated = $request->validate([
            'signature'     => ['nullable', 'string', 'starts_with:data:image/', 'max:500000'],
            // which late jobs to list: "1,5,9" of the ids the print dialog offered; `ticket_filter` says the dialog was used, so
            // that choosing none prints an empty list instead of falling back to every job
            'ticket_filter' => ['nullable', 'boolean'],
            'tickets'       => ['nullable', 'string', 'max:20000', 'regex:/^\d+(,\d+)*$/'],
            'note'          => ['nullable', 'string', 'max:1000'],
        ]);
        if (! empty($validated['signature'])) {
            $data['signature'] = ReportSignature::fromDataUri($validated['signature']);
        }

        $data['breachedTotal'] = count($data['breachedTickets']);
        if ($request->boolean('ticket_filter')) {
            $chosen = array_flip(array_map('intval', explode(',', (string) ($validated['tickets'] ?? ''))));
            $data['breachedTickets'] = array_values(array_filter($data['breachedTickets'], fn ($t) => isset($chosen[$t->id])));
        }

        $data['note'] = trim((string) ($validated['note'] ?? ''));
        $data['preparedBy'] = $request->user()?->name;

        $hospital = [
            'name_th'  => 'โรงพยาบาลพระปกเกล้า',
            'name_en'  => 'PHRAPOKKLAO HOSPITAL',
            'subtitle' => 'SLA Performance Summary Report',
            'logo'     => public_path('images/logoppk1.png'),
        ];

        $data['hospital'] = $hospital;
        $data['reportDate'] = Carbon::now();

        $pdf = Pdf::loadView('maintenance.sla.report', $data)
            ->setPaper('A4', 'portrait');

        $this->addPageFooter($pdf, $data['reportDate']);

        return $pdf->stream('sla-report-' . Carbon::now()->format('Y-m-d') . '.pdf');
    }

    /**
     * "รายงานสรุป SLA · ข้อมูล ณ …" on the left and "หน้า 1 / 2" on the right of every page. The page count is only known once the
     * document is laid out, so it is rendered first and the footer drawn onto each page after (dompdf's page_text, not a script).
     */
    private function addPageFooter(\Barryvdh\DomPDF\PDF $pdf, Carbon $reportDate): void
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('sarabun', 'normal');
        $size = 8.5;
        $grey = [0.39, 0.45, 0.55];
        $margin = 36.85;   // the 13 mm side margin of the report's @page
        $y = $canvas->get_height() - 22;

        $canvas->page_text($margin, $y, 'รายงานสรุป SLA · ข้อมูล ณ ' . ThaiDate::longWithTime($reportDate), $font, $size, $grey);

        $pageLabel = 'หน้า {PAGE_NUM} / {PAGE_COUNT}';
        $labelWidth = $metrics->getTextWidth('หน้า 99 / 99', $font, $size);
        $canvas->page_text($canvas->get_width() - $margin - $labelWidth, $y, $pageLabel, $font, $size, $grey);
    }

    /** A `?from=` / `?to=` date, or null when it is missing or not a date (a typo in the URL must not be a 500). */
    private function dateFromQuery(Request $request, string $key): ?Carbon
    {
        $value = $request->input($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function getSlaDashboardData(Request $request)
    {
        $jobTypes = \App\Models\MaintenanceRequestType::where('is_active', true)->orderBy('sort_order')->get();

        $from = $this->dateFromQuery($request, 'from');
        $to   = $this->dateFromQuery($request, 'to');

        // the view echoes and parses request('from') / request('to'): hand it clean Y-m-d values or nothing
        $request->merge(['from' => $from?->toDateString(), 'to' => $to?->toDateString()]);

        $start = $from?->startOfDay() ?? Carbon::now()->startOfYear();
        $end   = $to?->endOfDay() ?? Carbon::now()->endOfMonth();

        $requests = MaintenanceRequest::with(['department:id,name_th,name_en'])
            ->whereBetween('request_date', [$start, $end])
            ->get();

        $responseTimeSum = 0; $responseCount = 0;
        $acceptanceTimeSum = 0; $acceptanceCount = 0;
        $resolutionTimeSum = 0; $resolutionCount = 0;
        $complianceCount = 0; $resolvedTotal = 0;

        foreach ($requests as $req) {
            if ($req->acknowledged_at && $req->request_date) {
                $responseTimeSum += (int) $req->request_date->diffInMinutes($req->acknowledged_at);
                $responseCount++;
            }
            if ($req->accepted_at && $req->acknowledged_at) {
                $acceptanceTimeSum += (int) $req->acknowledged_at->diffInMinutes($req->accepted_at);
                $acceptanceCount++;
            }
            if ($req->resolved_at && $req->request_date) {
                // Resolution time from start of request
                $gross = (int) $req->request_date->diffInMinutes($req->resolved_at);
                $net = max(0, $gross - ($req->paused_duration_minutes ?? 0));
                $resolutionTimeSum += $net;
                $resolutionCount++;
                $resolvedTotal++;
                
                // Compliance Check
                if ($req->sla_due_date) {
                    if ($req->resolved_at <= $req->sla_due_date) {
                        $complianceCount++;
                    }
                } else {
                    // Fallback for legacy data/safety
                    if ($net <= (48 * 60)) $complianceCount++;
                }
            }
        }

        $nowDatetime = Carbon::now();
        $warningThreshold = $nowDatetime->copy()->addHours(4);
        $activeTickets = MaintenanceRequest::with(['reporter:id,name', 'technician:id,name', 'department:id,name_th,name_en', 'type:id,name'])
            ->whereNotIn('status', [
                MaintenanceRequest::STATUS_RESOLVED, 
                MaintenanceRequest::STATUS_CLOSED, 
                MaintenanceRequest::STATUS_CANCELLED, 
                MaintenanceRequest::STATUS_REJECTED
            ])
            ->whereNotNull('sla_due_date')
            ->get();

        $breachedTickets = []; $atRiskTickets = [];
        // slaDeadline(): a job on hold has its clock stopped, so it is late only if it was already late when it was put on hold
        foreach ($activeTickets as $ticket) {
            $deadline = $ticket->slaDeadline($nowDatetime);
            if ($nowDatetime->greaterThan($deadline)) {
                $breachedTickets[] = $ticket;
            } elseif ($warningThreshold->greaterThan($deadline)) {
                $atRiskTickets[] = $ticket;
            }
        }

        // เรียงจากเกินมากสุดไปน้อยสุด (เวลาที่น้อยที่สุดคือเกินมากที่สุด)
        usort($breachedTickets, fn($a, $b) => $a->slaDeadline($nowDatetime) <=> $b->slaDeadline($nowDatetime));
        usort($atRiskTickets, fn($a, $b) => $a->slaDeadline($nowDatetime) <=> $b->slaDeadline($nowDatetime));

        // Built up entirely by the loop below — the compliant branch increments
        // 'ทำตาม SLA' per resolved request, so it must start at 0 (seeding it
        // with $complianceCount double-counted every compliant ticket).
        $statusDist = ['ทำตาม SLA' => 0, 'เกินเวลา' => 0, 'มีความเสี่ยง' => 0, 'ตามกำหนด' => 0];
        $monthBreached = [];

        foreach ($requests as $req) {
            if ($req->resolved_at && $req->request_date) {
                $isCompliant = $req->sla_due_date 
                    ? ($req->resolved_at <= $req->sla_due_date) 
                    : (max(0, (int) $req->request_date->diffInMinutes($req->resolved_at) - ($req->paused_duration_minutes ?? 0)) <= (48 * 60));
                
                if ($isCompliant) {
                    $statusDist['ทำตาม SLA']++;
                } else {
                    $statusDist['เกินเวลา']++;
                    $monthBreached[] = $req;
                }
            } else {
                if (!in_array($req->status, [
                    MaintenanceRequest::STATUS_RESOLVED, 
                    MaintenanceRequest::STATUS_CLOSED, 
                    MaintenanceRequest::STATUS_CANCELLED, 
                    MaintenanceRequest::STATUS_REJECTED
                ])) {
                    if ($req->sla_due_date) {
                        $deadline = $req->slaDeadline($nowDatetime);
                        if ($nowDatetime->greaterThan($deadline)) {
                            $statusDist['เกินเวลา']++; 
                            $monthBreached[] = $req;
                        } elseif ($warningThreshold->greaterThan($deadline)) {
                            $statusDist['มีความเสี่ยง']++;
                        } else { 
                            $statusDist['ตามกำหนด']++; 
                        }
                    } else { 
                        $statusDist['ตามกำหนด']++; 
                    }
                }
            }
        }
        
        $breachesByDept = [];
        foreach ($monthBreached as $req) {
            $deptName = $req->department ? $req->department->name : 'ไม่ได้ระบุ';
            if (!isset($breachesByDept[$deptName])) $breachesByDept[$deptName] = 0;
            $breachesByDept[$deptName]++;
        }
        arsort($breachesByDept);

        $dashboard = [
            'avg_response_hours' => $responseCount > 0 ? round(($responseTimeSum / $responseCount) / 60, 1) : 0,
            'avg_acceptance_hours' => $acceptanceCount > 0 ? round(($acceptanceTimeSum / $acceptanceCount) / 60, 1) : 0,
            'avg_resolution_hours' => $resolutionCount > 0 ? round(($resolutionTimeSum / $resolutionCount) / 60, 1) : 0,
            'compliance_rate' => $resolvedTotal > 0 ? round(($complianceCount / $resolvedTotal) * 100, 1) : 0,
            'breached_count' => count($breachedTickets),
            'at_risk_count' => count($atRiskTickets),
        ];

        $chartLabels = []; $chartResolution = []; $chartCompliance = [];
        
        $chartStart = $start->copy()->startOfMonth();
        $chartEnd = $end->copy()->endOfMonth();
        
        if ((int) $chartStart->diffInMonths($chartEnd) > 60) {
            $chartStart = $chartEnd->copy()->subMonths(60);
        }

        $currentMonth = $chartStart->copy();
        while ($currentMonth <= $chartEnd) {
            $mStartObj = $currentMonth->copy()->startOfMonth();
            $mEndObj = $currentMonth->copy()->endOfMonth();
            $label = $mStartObj->translatedFormat('M Y');
            
            $mRequests = $requests->filter(function($req) use ($mStartObj, $mEndObj) {
                return $req->request_date && $req->request_date >= $mStartObj && $req->request_date <= $mEndObj;
            });

            $mResSum = 0; $mResCount = 0; $mCompCount = 0; $mTotalRes = 0;
            foreach ($mRequests as $req) {
                if ($req->resolved_at && $req->request_date) {
                    $mTotalRes++; $gross = (int) $req->request_date->diffInMinutes($req->resolved_at);
                    $net = max(0, $gross - ($req->paused_duration_minutes ?? 0));
                    $mResSum += $net; $mResCount++;
                    if ($req->sla_due_date) { if ($req->resolved_at <= $req->sla_due_date) $mCompCount++; }
                    else { if ($net <= (48 * 60)) $mCompCount++; }
                }
            }
            $chartLabels[] = $label;
            $chartResolution[] = $mResCount > 0 ? round(($mResSum / $mResCount) / 60, 1) : 0;
            $chartCompliance[] = $mTotalRes > 0 ? round(($mCompCount / $mTotalRes) * 100, 1) : 0;
            
            $currentMonth->addMonth();
        }

        $chartData = [
            'trend' => ['labels' => $chartLabels, 'resolution' => $chartResolution, 'compliance' => $chartCompliance],
            'distribution' => ['labels' => array_keys($statusDist), 'data' => array_values($statusDist)],
            'department' => ['labels' => array_keys($breachesByDept), 'data' => array_values($breachesByDept)]
        ];

        return compact('jobTypes', 'dashboard', 'breachedTickets', 'atRiskTickets', 'chartData')
            + ['periodStart' => $start, 'periodEnd' => $end];
    }

    /**
     * Update the default SLA times for a Maintenance Type.
     */
    public function updateTypeDefault(Request $request, $id)
    {
        $type = \App\Models\MaintenanceRequestType::findOrFail($id);

        $data = $request->validate([
            'default_response_minutes' => 'nullable|integer|min:0',
            'default_resolution_minutes' => 'nullable|integer|min:0',
        ]);

        $type->update($data);

        return back()->with('toast', \App\Support\Toast::success('อัปเดตเป้าหมายเวลา SLA ของประเภทงานเรียบร้อยแล้ว'));
    }

    /**
     * Update all SLA types in bulk.
     */
    public function bulkUpdateTypeDefault(Request $request)
    {
        $input = $request->validate([
            'types' => 'required|array',
            'types.*.default_response_minutes' => 'nullable|integer|min:0',
            'types.*.default_resolution_minutes' => 'nullable|integer|min:0',
        ]);

        foreach ($input['types'] as $id => $data) {
            \App\Models\MaintenanceRequestType::where('id', $id)->update($data);
        }

        return back()->with('toast', \App\Support\Toast::success('อัปเดตเป้าหมายเวลา SLA ทั้งหมดเรียบร้อยแล้ว'));
    }
}
