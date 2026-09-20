        {{-- Rating Popup Modal --}}
        <div x-show="ratingOpen" class="fixed inset-0 z-[9999] overflow-y-auto" style="display: none;"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

            <div class="flex items-center justify-center min-h-screen p-4">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="ratingOpen = false"></div>

                {{-- Modal Content --}}
                <div class="relative bg-white rounded-sm max-w-lg w-full overflow-hidden transform transition-all"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                    {{-- Header --}}
                    <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-white">
                        <div class="flex items-center gap-4">
                            <div class="flex flex-col">
                                <h3 class="text-[18px] font-bold text-slate-900 tracking-tight">ประเมินความพึงพอใจ</h3>
                                <p class="text-[12px] text-slate-400 font-bold tracking-widest uppercase mt-0.5">
                                    เลขที่ใบงาน
                                    #{{ $req->request_no ?? $req->id }}</p>
                            </div>
                        </div>
                        <x-ui.button @click="ratingOpen = false" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>

                    {{-- Form --}}
                    <form action="{{ route('maintenance.requests.rating.store', $req) }}" method="POST" class="p-8"
                        data-dirty-check="true">
                        @csrf
                        <div class="mb-8">
                            <div class="text-[14px] font-bold text-slate-700 uppercase tracking-tight mb-2">
                                หัวข้อการแจ้งซ่อม</div>
                            <p class="text-[16px] font-bold text-slate-800 tracking-tight leading-relaxed">
                                {{ $req->title }}</p>
                        </div>

                        {{-- Star Rating --}}
                        <div class="mb-10 text-center">
                            <label
                                class="block text-[15px] font-bold text-slate-800 mb-6">คุณพึงพอใจกับงานซ่อมนี้แค่ไหน?</label>
                            <div class="flex flex-row-reverse justify-center gap-2">
                                @for ($i = 5; $i >= 1; $i--)
                                    <input type="radio" id="star{{ $i }}" name="score"
                                        value="{{ $i }}" class="hidden peer" required>
                                    <label for="star{{ $i }}"
                                        class="cursor-pointer text-slate-300 hover:text-amber-400 peer-hover:text-amber-400 peer-checked:text-amber-500 transition-all transform hover:scale-125">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    </label>
                                @endfor
                            </div>
                            @error('score')
                                <p class="mt-2 text-rose-500 text-[12px] font-bold">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-8 p-1 bg-slate-50 rounded-sm border border-slate-100">
                            <textarea name="comment" rows="3" style="min-height:unset;height:auto;"
                                class="w-full bg-white rounded-sm border-none text-[14px] focus:ring-0 placeholder-slate-300 resize-none overflow-hidden p-4"
                                placeholder="แชร์ประสบการณ์ของคุณเพื่อการพัฒนาบริการ...">{{ old('comment') }}</textarea>
                            @error('comment')
                                <p class="mt-2 text-rose-500 text-[12px] font-bold px-4 pb-2">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end gap-3">
                            <x-ui.button @click="ratingOpen = false">ยกเลิก</x-ui.button>
                            <x-ui.button type="submit" variant="brand">บันทึกการประเมิน</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
