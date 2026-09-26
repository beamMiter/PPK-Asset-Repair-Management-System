{{--
  Rating dialog — the same frame as the other dialogs on this page (_modal_status_actions, _modal_assign …): backdrop
  bg-slate-900/40, a rounded-md bordered card, a header with a close button, a body of px-4 py-4 space-y-4, a footer of buttons.
  What is its own: the five big stars, and that they say what they mean ("พอใจมาก") as they are picked.
  Opened by the parent's `ratingOpen` (?rate=1, the "ประเมินงาน" buttons, or a refused submission).
--}}
@php
    $scoreNames = collect(range(1, 5))->mapWithKeys(fn ($s) => [$s => \App\Support\RatingLevel::scoreLabel($s)]);
@endphp

<div x-show="ratingOpen" x-cloak style="display: none;"
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4"
    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    @click.self="ratingOpen = false" @keydown.escape.window="ratingOpen = false">
    <div class="relative z-[10000] w-full max-w-lg max-h-[92vh] overflow-y-auto rounded-md border border-slate-200 bg-white ">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <div class="min-w-0">
                <div class="text-sm font-semibold text-slate-900">ประเมินความพึงพอใจ</div>
                <div class="text-[12px] text-slate-500">ใบงานเลขที่ #{{ $req->request_no ?? $req->id }}</div>
            </div>
            <x-ui.button @click="ratingOpen = false" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
        </div>

        <form action="{{ route('maintenance.requests.rating.store', $req) }}" method="POST" class="px-4 py-4 space-y-4"
            data-dirty-check="true" x-data="{ score: {{ (int) old('score', 0) }}, names: @js($scoreNames) }">
            @csrf

            <div>
                <div class="block text-sm font-medium text-slate-700">หัวข้อการแจ้งซ่อม</div>
                <p class="mt-1 text-sm font-semibold text-slate-900 break-words">{{ $req->title }}</p>
            </div>

            {{-- Star Rating --}}
            <div class="text-center">
                <div class="block text-sm font-medium text-slate-700 mb-[12px]">คุณพึงพอใจกับงานซ่อมนี้แค่ไหน?</div>
                <div class="flex flex-row-reverse justify-center gap-2">
                    @for ($i = 5; $i >= 1; $i--)
                        <input type="radio" id="star{{ $i }}" name="score" value="{{ $i }}" class="hidden peer"
                            x-model.number="score" required>
                        <label for="star{{ $i }}"
                            class="cursor-pointer text-slate-300 hover:text-amber-400 peer-hover:text-amber-400 peer-checked:text-amber-500 transition-all transform hover:scale-125">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" viewBox="0 0 20 20" fill="currentColor">
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                            <span class="sr-only">{{ $i }} ดาว</span>
                        </label>
                    @endfor
                </div>
                <p class="mt-2 h-5 text-[13px] font-semibold" :class="score ? 'text-slate-800' : 'text-slate-400'"
                    x-text="score ? names[score] : 'เลือกจำนวนดาว'"></p>
                @error('score')
                    <p class="mt-1 text-[12px] text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="rating-comment" class="block text-sm font-medium text-slate-700">ความคิดเห็นเพิ่มเติม
                    <span x-show="score > 0 && score <= 2" x-cloak class="text-rose-600">*</span></label>
                <textarea id="rating-comment" name="comment" rows="3" style="min-height:unset;height:auto;"
                    :required="score > 0 && score <= 2"
                    class="mt-2 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm resize-none overflow-hidden
                           focus:border-[#0F2D5C] focus:ring-2 focus:ring-[#0F2D5C]/15"
                    placeholder="แชร์ประสบการณ์ของคุณเพื่อการพัฒนาบริการ..." maxlength="1000" data-counter>{{ old('comment') }}</textarea>
                <p x-show="score > 0 && score <= 2" x-cloak class="mt-1 text-[12px] text-rose-600">ถ้าให้ 1–2 ดาว กรุณาระบุความคิดเห็นเพิ่มเติม</p>
                @error('comment')
                    <p class="mt-1 text-[12px] text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button @click="ratingOpen = false">ยกเลิก</x-ui.button>
                <x-ui.button type="submit" variant="brand">บันทึกการประเมิน</x-ui.button>
            </div>
        </form>
    </div>
</div>
