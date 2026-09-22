        {{-- Assign Modal --}}
        @can('assign', $req)
            <div id="assignModal"
                class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-3 sm:p-4">
                <div
                    class="relative z-[10000] w-full max-w-4xl overflow-hidden rounded-xl border {{ $line }} bg-white ">

                    {{-- Modal Header --}}
                    <div class="flex items-center justify-between border-b {{ $line }} px-6 py-4">
                        <div class="flex items-start gap-3 min-w-0">
                            <span class="mt-0.5 inline-flex h-10 w-10 items-center justify-center text-indigo-700">
                                <img src="/icon/technical-support.webp" class="h-9 w-9 object-contain" alt="Icon">
                            </span>
                            <div class="min-w-0">
                                <div class="text-[16px] font-semibold text-slate-900 leading-tight">มอบหมายทีมเจ้าหน้าที่</div>
                                <p class="text-[13px] text-slate-500">ค้นหาและเลือกเจ้าหน้าที่ที่ต้องการ</p>
                            </div>
                        </div>
                        <x-ui.button id="closeAssignModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>

                    <form method="POST" action="{{ $assignStoreUrl }}" data-dirty-check="true">
                        @csrf
                        <input type="hidden" id="assignSuggestRole" value="{{ $suggestRole }}">
                        <input type="hidden" name="update_team_flag" value="1">

                        <div class="grid grid-cols-1 lg:grid-cols-[380px,1fr] lg:h-[65vh]">

                            {{-- Left Sidebar --}}
                            <div
                                class="border-b lg:border-b-0 lg:border-r {{ $line }} bg-slate-50 flex flex-col min-h-0">

                                {{-- Controls --}}
                                <div class="p-5 space-y-4 flex-none">
                                    <div>
                                        <label class="block text-[13px] font-semibold text-slate-700 mb-1.5">ค้นหาชื่อ</label>
                                        <div class="relative">
                                            <span
                                                class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none">
                                                    <circle cx="11" cy="11" r="7" stroke="currentColor"
                                                        stroke-width="2" />
                                                    <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" />
                                                </svg>
                                            </span>
                                            <input id="assignSearch" type="text"
                                                class="w-full rounded-md border {{ $line }} bg-white pl-9 pr-3 py-2.5 text-[13px]
                                        focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400"
                                                placeholder="พิมพ์ชื่อเจ้าหน้าที่...">
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            class="block text-[13px] font-semibold text-slate-700 mb-1.5">กรองตามตำแหน่ง</label>
                                        <select id="assignRoleFilter"
                                            class="w-full rounded-md border {{ $line }} bg-white px-3 py-2.5 text-[13px]
                                    focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400">
                                            <option value="">— ทั้งหมด —</option>
                                            @foreach ($roleGroupsSorted as $roleCode => $users)
                                                <option value="{{ strtolower((string) $roleCode) }}">
                                                    {{ $roleLabels[$roleCode] ?? ($fallbackRoleLabels[$roleCode] ?? ucfirst((string) $roleCode)) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div id="assignSuggestHint" class="mt-1.5 text-[12px] text-indigo-600 hidden">
                                            ตัวกรองถูกตั้งค่าตามประเภทงานโดยอัตโนมัติ
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <x-ui.button id="assignSelectAllBtn" size="sm">เลือกทั้งหมด</x-ui.button>
                                        <x-ui.button id="assignClearAllBtn" size="sm">ล้างการเลือก</x-ui.button>
                                    </div>
                                </div>

                                {{-- Selected List — เติบโตเต็ม sidebar ที่เหลือ --}}
                                <div class="px-5 pb-4 border-t {{ $line }} pt-4 flex flex-col flex-1 min-h-0">
                                    <div class="flex items-center justify-between flex-none mb-2">
                                        <div class="text-[13px] font-semibold text-slate-700">เลือกแล้ว</div>
                                        <div class="text-[13px] text-slate-500" id="assignSelectedMeta">0 คน</div>
                                    </div>
                                    <div id="assignSelectedEmpty" class="text-[13px] text-slate-400 flex-none">
                                        ยังไม่ได้เลือกเจ้าหน้าที่
                                    </div>
                                    {{-- แต่ละ item แสดงชื่อเต็ม ไม่ตัด --}}
                                    <div id="assignSelectedList"
                                        class="space-y-1.5 overflow-y-auto pr-1 hidden flex-1 min-h-0"></div>
                                </div>

                                {{-- Footer ปุ่มย้ายมาอยู่ใต้ sidebar --}}
                                <div class="border-t {{ $line }} bg-slate-50 px-5 py-3 flex justify-end gap-2">
                                    <x-ui.button id="cancelAssignModalBtn">ยกเลิก</x-ui.button>
                                    <x-ui.button type="submit" variant="primary">บันทึก</x-ui.button>
                                </div>

                            </div>

                            {{-- Right List --}}
                            <div class="flex flex-col min-h-0">

                                {{-- Right Header --}}
                                <div
                                    class="px-5 py-3 border-b {{ $line }} bg-white flex items-center justify-between">
                                    <div class="text-[14px] font-semibold text-slate-800">
                                        รายชื่อเจ้าหน้าที่
                                        <span id="assignVisibleCount" class="text-slate-500 font-normal">(0)</span>
                                    </div>
                                </div>

                                {{-- Hint --}}
                                <div class="px-5 py-2 border-b {{ $line }} bg-slate-50">
                                    <p class="text-[12px] text-slate-400">
                                        ใช้ตัวกรองด้านซ้ายเพื่อค้นหาและเลือกเจ้าหน้าที่ได้รวดเร็ว
                                    </p>
                                </div>

                                {{-- List --}}
                                <div class="flex-1 min-h-0 overflow-y-auto" id="assignListScroll">
                                    @if ($roleGroupsSorted->isEmpty())
                                        <div class="px-5 py-10 text-center text-[14px] text-slate-500">
                                            ไม่พบข้อมูลเจ้าหน้าที่ในระบบ
                                        </div>
                                    @else
                                        @foreach ($roleGroupsSorted as $roleCode => $users)
                                            @php
                                                $roleTitle =
                                                    $roleLabels[$roleCode] ??
                                                    ($fallbackRoleLabels[$roleCode] ?? ucfirst((string) $roleCode));
                                                $roleCount = $users->count();
                                                $roleKey = strtolower(trim((string) $roleCode));
                                            @endphp
                                            <section class="border-b {{ $line }}" data-role-group="1"
                                                data-role-group-code="{{ $roleKey }}">

                                                {{-- Section Header --}}
                                                <div
                                                    class="sticky top-0 z-10 px-5 py-2 bg-slate-100 border-b {{ $line }}">
                                                    <div
                                                        class="text-[12px] font-bold text-slate-600 uppercase tracking-widest">
                                                        {{ $roleTitle }} ({{ $roleCount }})
                                                    </div>
                                                </div>

                                                <div class="divide-y divide-slate-100">
                                                    @foreach ($users as $worker)
                                                        @php
                                                            $roleLabelRow =
                                                                $worker->role_label ??
                                                                ($fallbackRoleLabels[$worker->role ?? 'unknown'] ??
                                                                    ($worker->role ?? 'unknown'));
                                                            $avatar = $worker->avatar_thumb_url ?? null;
                                                        @endphp
                                                        <label
                                                            class="assign-user-row flex items-center gap-3 px-5 py-2.5 hover:bg-indigo-50/30 cursor-pointer transition-colors"
                                                            data-role="{{ $roleKey }}"
                                                            data-name="{{ strtolower((string) $worker->name) }}"
                                                            data-display-name="{{ $worker->name }}"
                                                            data-role-label="{{ $roleLabelRow }}">

                                                            <input type="checkbox"
                                                                class="assign-user-checkbox h-4 w-4 flex-shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                                                data-role="{{ (string) $roleCode }}" name="user_ids[]"
                                                                value="{{ $worker->id }}" @checked($workers->contains('id', $worker->id))>

                                                            <div
                                                                class="h-8 w-8 flex-shrink-0 overflow-hidden rounded-full border border-slate-200 bg-slate-100">
                                                                @if ($avatar)
                                                                    <img src="{{ $avatar }}" alt="{{ $worker->name }}"
                                                                        class="h-full w-full object-cover">
                                                                @else
                                                                    <div
                                                                        class="grid h-full w-full place-items-center text-[12px] font-semibold text-slate-500 bg-slate-100 uppercase">
                                                                        {{ mb_substr($worker->name, 0, 1) }}
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            <div class="flex-1 min-w-0 truncate">
                                                                <span
                                                                    class="text-[14px] font-semibold text-slate-900">{{ $worker->name }}</span>
                                                            </div>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endforeach
                                    @endif
                                </div>

                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
