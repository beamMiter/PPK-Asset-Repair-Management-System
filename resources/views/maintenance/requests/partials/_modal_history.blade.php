        {{-- History Modal --}}
        <div id="historyModal"
            class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
            <div
                class="relative z-[10000] w-full max-w-2xl rounded-md border {{ $line }} bg-white overflow-hidden animate-in fade-in zoom-in duration-200">
                {{-- Header: same icon / text sizing as the assign-team dialog --}}
                <div class="flex items-center justify-between border-b {{ $line }} px-6 py-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="mt-0.5 inline-flex h-10 w-10 items-center justify-center text-slate-800">
                            <span class="material-symbols-outlined text-[36px]">history</span>
                        </span>
                        <div class="min-w-0">
                            <div class="text-[16px] font-semibold text-slate-900 leading-tight">ประวัติการดำเนินงาน</div>
                            <p class="text-[13px] text-slate-500">Operation & Status History Log</p>
                        </div>
                    </div>
                    <x-ui.button id="closeHistoryModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                </div>

                <div class="px-6 py-6 max-h-[60vh] overflow-y-auto bg-white custom-scrollbar-indigo">
                    @include('maintenance.requests.partials._timeline')
                </div>
            </div>
        </div>
