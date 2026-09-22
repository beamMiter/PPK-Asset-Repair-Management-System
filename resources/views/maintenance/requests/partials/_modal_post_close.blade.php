        {{-- Post-Close Action Modal --}}
        @if (session('show_post_close_modal'))
            <div id="postCloseModal"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
                <div
                    class="relative z-[10000] w-full max-w-md transform transition-all animate-in fade-in zoom-in duration-300">
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white ">
                        {{-- Icon Header --}}
                        <div class="bg-slate-50 px-6 py-8 text-center border-b border-slate-100">
                            <div
                                class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 mb-4 ">
                                <span class="material-symbols-outlined text-[48px]">task</span>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900">อนุมัติผลการซ่อมบำรุงเรียบร้อยแล้ว!</h3>
                            <p class="mt-2 text-sm text-slate-500">ขอบคุณที่ใช้บริการครับ คุณต้องการดำเนินการอย่างไรต่อ?
                            </p>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="p-6 flex flex-wrap justify-center gap-3">
                            @can('rate', $req)
                                <x-ui.button variant="warning" icon="star" :href="route('maintenance.requests.rating.create', $req->id)">ประเมินความพึงพอใจ</x-ui.button>
                            @endcan

                            <x-ui.button icon="visibility"
                                onclick="document.getElementById('postCloseModal').remove(); document.body.style.overflow = '';">
                                ดูรายละเอียดใบงาน
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                document.body.style.overflow = 'hidden';
            </script>
        @endif
