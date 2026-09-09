@php
    use App\Models\MaintenanceRequest as MR;
    use Carbon\Carbon;

    $startTime = $req->request_date ?? $req->created_at;

    // 1. Response Time
    $firstResponseTime = $req->acknowledged_at ?? $req->accepted_at;
    $responseTimeSeconds = $firstResponseTime ? (int) $startTime->diffInSeconds($firstResponseTime) : null;

    // 2. Total Hold Time
    $totalHoldSeconds = ($req->paused_duration_minutes ?? 0) * 60;
    if ($req->status === MR::STATUS_ON_HOLD && $req->on_hold_at) {
        $totalHoldSeconds += (int) $req->on_hold_at->diffInSeconds(now());
    }

    // 3. Repair Time (Net)
    $actualStart = $req->started_at ?? $req->accepted_at;

    if (in_array($req->status, [MR::STATUS_RESOLVED, MR::STATUS_CLOSED])) {
        $actualEnd = $req->resolved_at ?? ($req->closed_at ?? $req->updated_at);
    } elseif (in_array($req->status, [MR::STATUS_ACCEPTED, MR::STATUS_IN_PROGRESS, MR::STATUS_ON_HOLD])) {
        $actualEnd = clone now();
    } else {
        $actualStart = null; // not started yet
    }

    $repairTimeSeconds = 0;
    if ($actualStart && isset($actualEnd)) {
        $grossRepairSeconds = (int) $actualStart->diffInSeconds($actualEnd);
        $repairTimeSeconds = max(0, $grossRepairSeconds - $totalHoldSeconds);
    }

    if (!function_exists('formatExactDurationThai')) {
        function formatExactDurationThai($seconds)
        {
            if ($seconds === null || $seconds === false) {
                return '—';
            }
            if ($seconds <= 0) {
                return '0 นาที';
            }
            if ($seconds < 60) {
                return $seconds . ' วินาที';
            }
            $m = floor($seconds / 60);
            $s = $seconds % 60;
            if ($m < 60) {
                return $m . ' นาที ' . ($s > 0 ? $s . ' วิ' : '');
            }
            $h = floor($m / 60);
            $rm = $m % 60;
            if ($h < 24) {
                return $h . ' ชม. ' . ($rm > 0 ? $rm . ' นาที' : '');
            }
            $d = floor($h / 24);
            $rh = $h % 24;
            return $d . ' วัน ' . ($rh > 0 ? $rh . ' ชม.' : '');
        }
    }

    $isOverdue =
        $req->sla_due_date &&
        now()->gt($req->sla_due_date) &&
        !in_array($req->status, [MR::STATUS_RESOLVED, MR::STATUS_CLOSED]);
@endphp

<section>
    <div class="{{ $headCls }}">
        <div class="{{ $noCls }}">7</div>
        <div class="{{ $accentWrap }}">
            <span class="{{ $accentBar }}"></span>
            <div class="{{ $titleCls }}">การวิเคราะห์เวลา (SLA Analysis)</div>
            <div class="{{ $subCls }}">สรุปประสิทธิภาพการตอบรับและซ่อมบำรุงเชิงลึก</div>
        </div>
    </div>

    <div class="space-y-4 text-sm mt-2">
        @if ($req->sla_due_date)
            <div
                class="rounded-md border {{ $line }} {{ $isOverdue ? 'bg-rose-50 border-rose-200' : 'bg-emerald-50 border-emerald-200' }} px-3 py-2 flex items-center justify-between ">
                <div class="flex items-center gap-2">
                    <span
                        class="material-symbols-outlined text-[20px] {{ $isOverdue ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ $isOverdue ? 'error' : 'check_circle' }}
                    </span>
                    <span class="font-bold {{ $isOverdue ? 'text-rose-700' : 'text-emerald-700' }}">
                        {{ $isOverdue ? 'สถานะ: เกินกำหนด SLA' : 'สถานะ: ภายใต้กำหนด SLA' }}
                    </span>
                </div>
                <div class="text-[12px] font-bold {{ $isOverdue ? 'text-rose-600' : 'text-emerald-600' }}">
                    ครบกำหนด: {{ $req->sla_due_date->format('d/m/Y H:i') }}
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-tight">เวลาตอบรับ (Response Time)
                </div>
                <div class="mt-1.5 rounded-md border {{ $line }} bg-white px-3 py-2.5">
                    <div class="font-bold text-slate-900 text-[15px] leading-none">
                        {{ $responseTimeSeconds ? formatExactDurationThai($responseTimeSeconds) : '—' }}
                    </div>
                    <div class="mt-1.5 text-[11px] text-slate-400 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[12px]">target</span>
                        เป้าหมาย: {{ $req->type?->default_response_minutes ?? 0 }} นาที
                    </div>
                </div>
            </div>

            <div>
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-tight">เวลาดำเนินการสุทธิ (Repair
                    Time)</div>
                <div class="mt-1.5 rounded-md border {{ $line }} bg-white px-3 py-2.5">
                    <div class="font-bold text-indigo-700 text-[15px] leading-none">
                        {{ formatExactDurationThai($repairTimeSeconds) }}
                    </div>
                    <div class="mt-1.5 text-[11px] text-slate-400 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[12px]">history</span>
                        เป้าหมาย: {{ $req->type?->default_resolution_minutes ?? 0 }} นาที
                    </div>
                </div>
            </div>
        </div>

        <div
            class="rounded-md border {{ $line }} bg-slate-50 px-3 py-3 flex items-center justify-between border-dashed">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-slate-400 text-[18px]">pause_circle</span>
                <span
                    class="text-xs font-semibold text-slate-600 uppercase tracking-tight">เวลาหยุดการซ่อมบำรุงชั่วคราวรวม
                    (Total Hold Time)</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="font-bold text-slate-800 text-[14px]">
                    {{ formatExactDurationThai($totalHoldSeconds) }}
                </span>
                <span class="text-[10px] text-slate-400 font-medium italic">(หักลบจาก SLA อัตโนมัติ)</span>
            </div>
        </div>
    </div>
</section>
