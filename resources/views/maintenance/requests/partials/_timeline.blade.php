@php
    use App\Models\MaintenanceRequest as MR;
    use Carbon\Carbon;
    
    // Sort ascending for chronological calculation, then we'll reverse for display
    $logsChronological = $req->logs->sortBy('created_at')->values();
    $statusLabels = MR::statusLabels();
    
    // Function to format duration nicely
    if (!function_exists('formatDurationThai')) {
        function formatDurationThai($seconds) {
            if ($seconds < 60) return $seconds . " วินาที";
            $mins = floor($seconds / 60);
            if ($mins < 60) return $mins . " นาที";
            $hours = floor($mins / 60);
            $remainMins = $mins % 60;
            if ($hours < 24) return $hours . " ชม. " . ($remainMins > 0 ? $remainMins . " นาที" : "");
            $days = floor($hours / 24);
            $remainHours = $hours % 24;
            return $days . " วัน " . ($remainHours > 0 ? $remainHours . " ชม." : "");
        }
    }
@endphp

<div class="space-y-6">
    @if($logsChronological->isEmpty())
        <div class="text-center py-10">
            <span class="material-symbols-outlined text-[48px] text-slate-200">history_toggle_off</span>
            <p class="mt-2 text-sm text-slate-400">ยังไม่มีบันทึกประวัติการดำเนินงาน</p>
        </div>
    @else
        <div class="relative pl-8 ml-4 border-l-2 border-slate-100 space-y-8">
            @php
                // Reverse for display (latest first)
                $displayLogs = $logsChronological->reverse();
            @endphp
            
            @foreach($displayLogs as $index => $log)
                @php
                    $toStatus = strtolower((string)$log->to_status);
                    
                    // Find duration spent in this state (time until next log in chronological order)
                    $chronoIndex = $logsChronological->search(fn($l) => $l->id === $log->id);
                    $nextLog = $logsChronological->get($chronoIndex + 1);
                    $durationText = null;
                    
                    if ($nextLog) {
                        $diffSeconds = (int) $log->created_at->diffInSeconds($nextLog->created_at);
                        $durationText = formatDurationThai($diffSeconds);
                    } elseif ($log->created_at->diffInHours(now()) < 8760) { // If it's the latest log, show time since then if relevant
                         // Optional: show "กำลังดำเนินการมาแล้ว..."
                    }

                    $dotColor = match($toStatus) {
                        'pending'      => 'bg-amber-400',
                        'acknowledged' => 'bg-blue-400',
                        'accepted'     => 'bg-indigo-400',
                        'in_progress'  => 'bg-sky-500',
                        'on_hold'      => 'bg-rose-400',
                        'resolved'     => 'bg-emerald-500',
                        'closed'       => 'bg-emerald-700',
                        'cancelled'    => 'bg-slate-400',
                        default        => 'bg-slate-300'
                    };

                    $icon = match($log->action) {
                        'create_request' => 'add_circle',
                        'assign_technician' => 'person_add',
                        'transition' => match($toStatus) {
                            'on_hold' => 'pause_circle',
                            'in_progress' => 'directions_run',
                            'resolved' => 'task_alt',
                            'closed' => 'verified',
                            'cancelled' => 'cancel',
                            default => 'sync'
                        },
                        default => 'info'
                    };

                    // Translation Logic
                    $fromLabel = $log->from_status ? ($statusLabels[strtolower($log->from_status)] ?? $log->from_status) : 'เริ่มต้น';
                    $toLabel = $log->to_status ? ($statusLabels[strtolower($log->to_status)] ?? $log->to_status) : '-';
                    $actorName = $log->user->name ?? 'ระบบ';

                    // Parse and extract the actual note (removing the English prefix if it exists)
                    $displayNote = $log->note;
                    $actualNote = '';
                    
                    if (preg_match('/^\[(.*?) -> (.*?)\](.*)$/', $displayNote, $matches)) {
                        $actualNote = trim($matches[3]);
                    } elseif (!str_starts_with($displayNote, '[')) {
                        $actualNote = trim($displayNote);
                    }

                    // Determine clear Action Title based on standard system language
                    $actionTitle = $statusLabels[$toStatus] ?? 'อัปเดตสถานะ';
                    if ($toStatus === 'in_progress' && strtolower((string)$log->from_status) === 'on_hold') {
                        $actionTitle = 'ดำเนินการต่อ (ยกเลิกการหยุดซ่อมชั่วคราว)';
                    }
                    if ($log->action === 'create_request') {
                        $actionTitle = 'สร้างใบแจ้งซ่อมใหม่ (เข้าระบบ)';
                    }
                    if ($log->action === 'assign_technician') {
                        $actionTitle = 'มอบหมายเจ้าหน้าที่ผู้รับผิดชอบ';
                    }

                    // Label for the duration type
                    $isHold = ($toStatus === 'on_hold');
                    $durationLabel = $isHold ? 'หยุดซ่อมชั่วคราว: ' : 'ใช้เวลา: ';
                    $durationClass = $isHold 
                        ? 'text-rose-600 bg-rose-50 border-rose-100' 
                        : 'text-amber-600 bg-amber-50 border-amber-100';
                @endphp

                <div class="relative">
                    {{-- Timeline Dot --}}
                    <div class="absolute -left-[45px] top-0 flex h-8 w-8 items-center justify-center rounded-full bg-white border-2 border-slate-50 z-10">
                        <span class="material-symbols-outlined text-[18px] {{ str_replace('bg-', 'text-', $dotColor) }}">
                            {{ $icon }}
                        </span>
                    </div>

                    {{-- Content Card --}}
                    <div class="bg-slate-50/50 rounded-lg p-4 border border-slate-200 hover:border-indigo-200 hover:bg-white transition-all group">
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <div class="flex flex-col">
                                <span class="text-sm font-bold text-slate-800">
                                    {{ $actionTitle }}
                                </span>
                                @if($actualNote !== '')
                                    <span class="text-[13px] text-slate-600 mt-1.5 pl-2.5 border-l-[3px] border-indigo-200">
                                        {{ $actualNote }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex flex-col items-end gap-1.5">
                                @if($durationText)
                                    <span class="text-[10px] font-bold {{ $durationClass }} px-2 py-0.5 rounded-full border flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px]">{{ $isHold ? 'pause_circle' : 'schedule' }}</span>
                                        {{ $durationLabel }}{{ $durationText }}
                                    </span>
                                @endif
                                <span class="text-[11px] font-medium text-slate-400 bg-white px-2 py-0.5 rounded-full border border-slate-100">
                                    {{ $log->created_at->format('d/m/Y H:i') }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1.5 text-[12px] text-slate-600">
                                <div class="h-5 w-5 rounded-full bg-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-500">
                                    {{ mb_substr($actorName, 0, 1) }}
                                </div>
                                <span>{{ $actorName }}</span>
                            </div>
                            
                            @if($log->from_status || $log->to_status)
                                <span class="text-slate-300 text-[10px]">•</span>
                                <div class="flex items-center gap-1.5 text-[10px] bg-white px-2 py-0.5 rounded-md border border-slate-100">
                                    <span class="text-slate-400 font-bold lowercase">{{ $fromLabel }}</span>
                                    <span class="material-symbols-outlined text-[10px] text-slate-300">trending_flat</span>
                                    <span class="text-indigo-600 font-bold lowercase">{{ $toLabel }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
