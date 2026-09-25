@php
    use App\Models\MaintenanceRequest as MR;

    // Sort ascending for chronological calculation, then we'll reverse for display
    $logsChronological = $req->logs->sortBy('created_at')->values();
    $statusLabels = MR::statusLabels();
    $labelToCode = array_flip($statusLabels);

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

    // One row per status: icon, dot colours and the accent bar of the note. Every status has its own icon — the two
    // "done" states (resolved / closed) used to share look-alike ticks; approval is now a paper with a tick, filled.
    $statusStyle = [
        'pending'      => ['icon' => 'hourglass_empty', 'dot' => 'bg-amber-50 text-amber-600',    'bar' => 'border-amber-300'],
        'acknowledged' => ['icon' => 'visibility',      'dot' => 'bg-blue-50 text-blue-600',      'bar' => 'border-blue-300'],
        'accepted'     => ['icon' => 'thumb_up',        'dot' => 'bg-indigo-50 text-indigo-600',  'bar' => 'border-indigo-300'],
        'in_progress'  => ['icon' => 'directions_run',  'dot' => 'bg-sky-50 text-sky-600',        'bar' => 'border-sky-300'],
        'on_hold'      => ['icon' => 'pause_circle',    'dot' => 'bg-rose-50 text-rose-600',      'bar' => 'border-rose-300'],
        'resolved'     => ['icon' => 'task_alt',        'dot' => 'bg-emerald-50 text-emerald-600', 'bar' => 'border-emerald-300'],
        'closed'       => ['icon' => 'task',            'dot' => 'bg-emerald-600 text-white',     'bar' => 'border-emerald-500'],
        'completed'    => ['icon' => 'task_alt',        'dot' => 'bg-emerald-50 text-emerald-600', 'bar' => 'border-emerald-300'],
        'cancelled'    => ['icon' => 'cancel',          'dot' => 'bg-slate-100 text-slate-500',   'bar' => 'border-slate-300'],
        'rejected'     => ['icon' => 'block',           'dot' => 'bg-rose-50 text-rose-600',      'bar' => 'border-rose-300'],
    ];
    $neutralStyle = ['icon' => 'info', 'dot' => 'bg-slate-100 text-slate-500', 'bar' => 'border-slate-300'];

    // Events that are not a status change
    $eventStyle = [
        'create_request'    => ['icon' => 'add_circle',  'dot' => 'bg-amber-50 text-amber-600',   'bar' => 'border-amber-300',  'title' => 'สร้างใบแจ้งซ่อมใหม่'],
        'assign_technician' => ['icon' => 'person_add',  'dot' => 'bg-indigo-50 text-indigo-600', 'bar' => 'border-indigo-300', 'title' => 'มอบหมายเจ้าหน้าที่ผู้รับผิดชอบ'],
        'update_request'    => ['icon' => 'edit_note',   'dot' => 'bg-slate-100 text-slate-600',  'bar' => 'border-slate-300',  'title' => 'แก้ไขข้อมูลใบแจ้งซ่อม'],
        'note'              => ['icon' => 'sticky_note_2', 'dot' => 'bg-slate-100 text-slate-600', 'bar' => 'border-slate-300', 'title' => 'บันทึกเพิ่มเติม'],
    ];

    // A status column holds a code ("in_progress"); an old note prefix may hold a code or a Thai label.
    $toCode = function ($token) use ($statusLabels, $labelToCode) {
        $token = trim((string) $token);
        if ($token === '') return null;
        return isset($statusLabels[strtolower($token)]) ? strtolower($token) : ($labelToCode[$token] ?? null);
    };
@endphp

<div>
    @if($logsChronological->isEmpty())
        <div class="py-10">
            <x-ui.empty-state icon="history_toggle_off">ยังไม่มีบันทึกประวัติการดำเนินงาน</x-ui.empty-state>
        </div>
    @else
        {{-- Latest first. Each item draws the rail down to the next dot (rail centre = dot centre = 18px). --}}
        <ol class="space-y-5">
            @foreach($logsChronological->reverse() as $log)
                @php
                    $action = (string) $log->action;
                    $note = trim((string) $log->note);

                    // "[from -> to] text": the text is the note; the bracket is only used when the columns are empty.
                    $noteFrom = $noteTo = null;
                    $actualNote = $note;
                    if (preg_match('/^\[(.*?)\s*->\s*(.*?)\]\s*(.*)$/su', $note, $m)) {
                        $noteFrom = $toCode($m[1]);
                        $noteTo = $toCode($m[2]);
                        $actualNote = trim($m[3]);
                    } elseif (str_starts_with($note, '[')) {
                        $actualNote = '';
                    }

                    $isCreate = $action === 'create_request';
                    $isAssign = $action === 'assign_technician';
                    $fromCode = $toCode($log->from_status) ?? $noteFrom;
                    $toStatus = ($isCreate || $isAssign) ? null : ($toCode($log->to_status) ?? $noteTo);

                    if ($toStatus) {
                        $style = $statusStyle[$toStatus] ?? $neutralStyle;
                        $actionTitle = $statusLabels[$toStatus];
                        if ($toStatus === 'in_progress' && $fromCode === 'on_hold') {
                            $actionTitle = 'ดำเนินการต่อ (ยกเลิกการหยุดซ่อมชั่วคราว)';
                        }
                    } else {
                        $event = $eventStyle[$action] ?? null;
                        $style = $event ?? $neutralStyle;
                        $actionTitle = $event['title'] ?? 'อัปเดตรายการ';
                    }

                    // Say where the status came from in words — only when there really was a previous status.
                    $fromText = ($toStatus && $fromCode && $fromCode !== $toStatus) ? $statusLabels[$fromCode] : null;

                    // "Created" already says it; do not repeat the same sentence as a note.
                    if ($isCreate && str_starts_with($actualNote, 'สร้างใบแจ้งซ่อมใหม่')) {
                        $actualNote = '';
                    }

                    // Time spent in this state = until the next log in chronological order
                    $chronoIndex = $logsChronological->search(fn($l) => $l->id === $log->id);
                    $nextLog = $logsChronological->get($chronoIndex + 1);
                    $durationText = $nextLog
                        ? trim(formatDurationThai((int) $log->created_at->diffInSeconds($nextLog->created_at)))
                        : null;
                    $isHold = ($toStatus === 'on_hold');

                    $actorName = $log->user->name ?? 'ระบบ';
                @endphp

                <li class="relative pl-[52px]">
                    @unless($loop->last)
                        <span class="absolute left-[17px] top-[18px] -bottom-5 w-0.5 bg-slate-200" aria-hidden="true"></span>
                    @endunless

                    {{-- Dot --}}
                    <span class="absolute left-0 top-0 z-10 flex h-9 w-9 items-center justify-center rounded-full ring-4 ring-white {{ $style['dot'] }}">
                        <span class="material-symbols-outlined text-[20px]">{{ $style['icon'] }}</span>
                    </span>

                    {{-- Card --}}
                    <div class="rounded-xl border border-slate-200 bg-white p-[16px]">
                        <div class="flex flex-wrap items-start justify-between gap-x-[12px] gap-y-1">
                            <div class="min-w-0">
                                <div class="text-[14px] font-semibold leading-snug text-slate-900">{{ $actionTitle }}</div>
                                @if($fromText)
                                    <div class="mt-0.5 text-[12px] text-slate-500">
                                        เปลี่ยนจาก <span class="font-medium text-slate-700">{{ $fromText }}</span>
                                    </div>
                                @endif
                            </div>
                            <time class="whitespace-nowrap text-[12px] text-slate-500" datetime="{{ $log->created_at->toIso8601String() }}">
                                {{ $log->created_at->format('d/m/Y H:i') }}
                            </time>
                        </div>

                        @if($actualNote !== '')
                            <p class="mt-2 border-l-[3px] {{ $style['bar'] }} pl-[10px] text-[13px] leading-relaxed text-slate-600">{{ $actualNote }}</p>
                        @endif

                        <div class="mt-[12px] flex flex-wrap items-center justify-between gap-x-[12px] gap-y-1 border-t border-slate-100 pt-[12px]">
                            <div class="flex min-w-0 items-center gap-2 text-[12px] text-slate-600">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[12px] font-semibold text-slate-600">{{ mb_substr($actorName, 0, 1) }}</span>
                                <span class="truncate">{{ $actorName }}</span>
                            </div>

                            @if($durationText)
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[12px] font-medium {{ $isHold ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-600' }}">
                                    <span class="material-symbols-outlined text-[14px]">{{ $isHold ? 'pause_circle' : 'schedule' }}</span>
                                    {{ $isHold ? 'หยุดซ่อมชั่วคราว' : 'อยู่ในสถานะนี้' }} {{ $durationText }}
                                </span>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
