    <style>
        .animate-spin-slow {
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .ms {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>

    <div class="w-full bg-slate-50 border-b {{ $line }}">
        <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 py-4">

            {{-- Row 1: Title + Actions --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center">
                        <img src="/icon/maintenance1.webp" class="h-7 w-7 object-contain"
                            style="filter: invert(24%) sepia(87%) saturate(1469%) hue-rotate(139deg) brightness(91%) contrast(102%);"
                            alt="Icon">
                    </span>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <h1 class="text-[18px] sm:text-[20px] font-semibold text-slate-900 leading-tight">
                                ทะเบียนแจ้งซ่อม
                            </h1>
                            <span class="text-slate-400 text-[13px] font-semibold">
                                #{{ $req->request_no ?? $req->id }}
                            </span>
                        </div>
                        <div class="mt-0.5 text-[12px] text-slate-500 flex flex-wrap gap-x-3">
                            @if ($req->updated_at)
                                <span>อัปเดต: <span
                                        class="font-medium text-slate-700">{{ $req->updated_at->format('Y-m-d H:i') }}</span></span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Action buttons: workflow left, nav right --}}
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <div class="w-px h-6 bg-slate-200 mx-1 hidden sm:block"></div>

                    {{-- Workflow buttons --}}
                    @if ($canAcknowledge)
                        <form method="POST" action="{{ route('maintenance.requests.acknowledge', $req->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="brand" icon="approval_delegation">รับทราบ</x-ui.button>
                        </form>
                    @endif

                    @if ($canAccept)
                        <form method="POST" action="{{ route('maintenance.requests.accept', $req->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="info" icon="check">รับเรื่อง</x-ui.button>
                        </form>
                    @endif

                    @if ($canStart)
                        <form method="POST" action="{{ route('maintenance.requests.start', $req->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="primary" icon="keyboard_arrow_right">ดำเนินการ</x-ui.button>
                        </form>
                    @endif

                    @if ($canHold)
                        <x-ui.button id="openHoldModalBtn" variant="warning" icon="pause_circle">หยุดชั่วคราว</x-ui.button>
                    @endif

                    @if ($canResume)
                        <form method="POST" action="{{ route('maintenance.requests.resume', $req->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="info" icon="keyboard_double_arrow_right">กลับเข้าดำเนินการ</x-ui.button>
                        </form>
                    @endif

                    @if ($canResolve)
                        <x-ui.button id="openResolveModalBtn" variant="primary" icon="task_alt">เสร็จสิ้น</x-ui.button>
                    @endif

                    @if ($canClose)
                        <form method="POST" action="{{ route('maintenance.requests.close', $req->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="primary" icon="task">อนุมัติปิดงาน</x-ui.button>
                        </form>
                    @endif

                    @if ($req->status === \App\Models\MaintenanceRequest::STATUS_CLOSED)
                        @can('rate', $req)
                            <x-ui.button x-data @click="$dispatch('open-rating-modal')" variant="warning" icon="star">ประเมินความพึงพอใจ</x-ui.button>
                        @endcan
                    @endif

                    @if ($canReject)
                        <x-ui.button id="openRejectModalBtn" variant="danger" icon="block">ไม่รับเรื่อง</x-ui.button>
                    @endif

                    @if ($canCancel)
                        <x-ui.button id="openCancelModalBtn" variant="neutral" icon="cancel">ยกเลิกการซ่อมบำรุง</x-ui.button>
                    @endif

                    {{-- Tools + navigation, same order as the asset page: actions first, กลับ last.
                         Direct children of the row above so every gap is the same gap-2. --}}
                    @if ($canUpdate)
                        <x-ui.button :href="route('maintenance.requests.edit', $req->id)" icon="edit">แก้ไข</x-ui.button>
                    @endif

                    <x-ui.button :href="route('maintenance.requests.work-order', $req->id)" target="_blank" icon="print">พิมพ์ PDF</x-ui.button>

                    <x-ui.back-button :fallback="route('maintenance.requests.index')" />
                </div>
            </div>

            {{-- Row 2: Progress + history. The history log is the status timeline, so it sits with the
                 progress bar (labelled, with a count) instead of as an icon in the crowded action row. --}}
            <div class="mt-4 flex items-center justify-between gap-3 px-2 sm:px-4">
                <div class="hidden sm:block text-[13px] font-semibold text-slate-600 whitespace-nowrap">ความคืบหน้า</div>

                <x-ui.button id="openHistoryModalBtn" icon="history" class="ml-auto">
                    ประวัติการดำเนินงาน
                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600">{{ $req->logs->count() }}</span>
                </x-ui.button>
            </div>

            {{-- Progress bar --}}
            <div class="w-full px-2 sm:px-4 mt-3 overflow-x-auto pb-4">
                <div class="relative w-full min-w-[500px] sm:min-w-full">
                    <div class="absolute top-[22px] left-0 w-full h-[6px] bg-slate-200 rounded-full z-0"></div>
                    <div class="absolute top-[22px] left-0 h-[6px] bg-[#1e3a8a] rounded-full z-0 transition-all duration-700 ease-out"
                        style="width: {{ $lineWidth }};"></div>

                    <div class="relative z-10 flex justify-between w-full">
                        @php
                            $steps = [
                                1 => 'แจ้งเรื่อง',
                                2 => 'รับทราบแล้ว',
                                3 => 'รับเรื่องแล้ว',
                                4 => 'กำลังดำเนินการ',
                                5 => 'เสร็จสิ้น',
                            ];
                        @endphp

                        @foreach ($steps as $key => $label)
                            @php
                                $isDone = $level > $key || ($key == 5 && $level >= 5);
                                $isCurrent = $level == $key && $key != 5;
                                $dateVal = $dates[$key] ?? null;
                            @endphp

                            <div class="flex flex-col items-center w-32">
                                <div
                                    class="w-[44px] h-[44px] rounded-full flex items-center justify-center transition-all duration-300
                                    {{ $isDone ? 'bg-[#408a5c] border-4 border-[#408a5c]' : '' }}
                                    {{ $isCurrent ? 'bg-white' : '' }}
                                    {{ !$isDone && !$isCurrent ? 'bg-slate-200 border-4 border-slate-200' : '' }}">
                                    @if ($isDone)
                                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    @elseif($isCurrent)
                                        <div class="relative w-full h-full">
                                            <div class="absolute inset-0 rounded-full border-[4px] border-slate-100"></div>
                                            <div
                                                class="absolute inset-0 rounded-full border-[4px] border-t-blue-600 border-r-transparent border-b-transparent border-l-transparent animate-spin-slow">
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-2 text-center">
                                    <p
                                        class="text-[13px] font-bold {{ $isCurrent || $isDone ? 'text-slate-900' : 'text-slate-400' }}">
                                        {{ $label }}
                                    </p>
                                    @if ($isCurrent)
                                        <p class="text-[11px] font-medium text-[#1e3a8a] mt-0.5">(ปัจจุบัน)</p>
                                    @elseif($dateVal)
                                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $fmt($dateVal) }}</p>
                                    @else
                                        <p class="h-[16px]"></p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </div>
