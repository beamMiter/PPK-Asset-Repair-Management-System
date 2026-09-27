@extends('layouts.app')

@section('title', 'Notifications')

{{-- Font Awesome is only used on this page (6 icons); it is not loaded by the layout --}}
@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
@endpush

@section('content')
    @php
        $primary = '#0F2D5C';
        $currentSound = $currentSound ?? 'new-request.mp3';
    @endphp

    <div class="w-full flex flex-col">
        <div x-data="{ showConfig: window.innerWidth >= 768 }" class="sticky top-16 z-20 bg-white/90 backdrop-blur border-b border-slate-200">
            <div class="px-4 md:px-6 lg:px-8 py-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        {{-- Same glyph as the sidebar's notifications item --}}
                        <span class="material-symbols-outlined text-[32px] text-[#0F2D5C] mt-0.5"
                            aria-hidden="true">notifications_active</span>
                        <div>
                            <h1 class="text-[17px] font-semibold text-slate-900">ตั้งค่า - การแจ้งเตือน</h1>
                            <p class="text-[13px] text-slate-600">จัดการเสียงและคลังไฟล์แจ้งเตือนในระบบ</p>
                        </div>
                    </div>

                    {{-- Mobile Toggle --}}
                    <x-ui.button @click="showConfig = !showConfig" icon="settings" class="md:hidden">ตั้งค่า</x-ui.button>
                </div>

                <div x-show="showConfig" x-collapse x-cloak>
                    <form action="{{ route('settings.notifications.update_sound') }}" method="POST"
                        class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end border-t border-slate-100 pt-4">
                        @csrf
                        @method('PATCH')
                        <div class="md:col-span-4 lg:col-span-3 min-w-0">
                            <label
                                class="mb-1 block text-[12px] font-medium text-slate-500 uppercase">เสียงแจ้งเตือนที่ใช้งานอยู่</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                    <i class="fa-solid fa-bell"></i>
                                </span>
                                <select name="notification_sound"
                                    class="w-full h-11 rounded-md border border-slate-200 bg-white pl-10 pr-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-[{{ $primary }}]/35">
                                    @foreach ($sounds as $sound)
                                        <option value="{{ $sound }}" @selected($currentSound == $sound)>
                                            {{ $sound == 'new-request.mp3' ? 'ระบบมาตรฐาน (Default)' : $sound }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="md:col-span-4 lg:col-span-3 flex flex-wrap items-center gap-2">
                            <x-ui.button onclick="previewSound()" icon="play_arrow">ทดสอบ</x-ui.button>
                            <x-ui.button type="submit" variant="brand" icon="save">บันทึกการเลือก</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="px-4 md:px-6 lg:px-8 py-8 space-y-10">
            <section>
                <form action="{{ route('settings.notifications.upload_sound') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="w-full border-2 border-dashed border-slate-200 rounded-xl bg-slate-50/30 overflow-hidden">

                        <div
                            class="relative w-full py-12 flex flex-col items-center justify-center group-hover:bg-white transition-all">
                            {{-- data-max-kb / data-ext: layout/file-guard.js refuses a file over the limit or of another kind with a toast, as it is
                                 chosen. The limit is the server's own (NotificationSettingController::SOUND_MAX_KB). Cancelling the file dialog
                                 leaves no file: the label goes back to the prompt instead of throwing. --}}
                            <input type="file" name="sound_file" id="sound_file" accept=".mp3,.wav" required
                                data-max-kb="{{ \App\Http\Controllers\Settings\NotificationSettingController::SOUND_MAX_KB }}" data-ext="mp3,wav"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                onchange="document.getElementById('file-name-display').innerText = this.files[0] ? this.files[0].name : 'คลิกเพื่อเลือกไฟล์ .mp3 หรือ .wav'">

                            <i class="fa-solid fa-music text-slate-300 mb-5" style="font-size: 50px !important;"></i>

                            <div class="text-center">
                                <p id="file-name-display" class="text-[15px] text-slate-600 font-medium">
                                    คลิกเพื่อเลือกไฟล์ .mp3 หรือ .wav
                                </p>
                                <p class="text-[12px] text-slate-400 mt-2">(ขนาดไม่เกิน {{ intdiv(\App\Http\Controllers\Settings\NotificationSettingController::SOUND_MAX_KB, 1024) }}MB)</p>
                            </div>
                        </div>

                        <div class="px-4 pb-4">
                            <button type="submit"
                                class="w-full bg-[#3d8b63] hover:bg-[#2d6a4c] text-white py-3.5 rounded-lg text-[15px] font-semibold transition-colors border-none">
                                เพิ่มเข้าคลังเสียง
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <section>
                <div class="border-t border-slate-200">
                    @foreach ($sounds as $sound)
                        <div
                            class="flex items-center justify-between py-4 border-b border-slate-100 hover:bg-slate-50/50 transition-colors px-2">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                                    <i class="fa-solid fa-volume-high text-[14px]"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-slate-700 text-[14px]">{{ $sound }}</span>
                                    @if ($currentSound == $sound)
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <i class="fa-solid fa-circle-check text-emerald-500 text-[10px]"></i>
                                            <span
                                                class="text-[11px] text-emerald-600 font-bold uppercase tracking-tight">กำลังใช้งาน</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-6">
                                <span
                                    class="hidden md:block bg-slate-100 text-slate-500 px-2 py-0.5 rounded text-[10px] font-bold uppercase">
                                    {{ pathinfo($sound, PATHINFO_EXTENSION) }}
                                </span>
                                <div class="w-[40px] flex justify-end">
                                    @if ($sound !== 'new-request.mp3')
                                        <form action="{{ route('settings.notifications.destroy_sound') }}" method="POST">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="file_name" value="{{ $sound }}">
                                            <button type="submit"
                                                class="text-slate-300 hover:text-rose-600 transition-colors">
                                                <i class="fa-solid fa-trash-can text-[16px]"></i>
                                            </button>
                                        </form>
                                    @else
                                        <i class="fa-solid fa-shield-halved text-slate-200 text-[16px]"></i>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    <audio id="soundPreview" preload="none"></audio>

    @push('scripts')
        <script>
            function previewSound() {
                const select = document.querySelector('select[name="notification_sound"]');
                const player = document.getElementById('soundPreview');
                player.src = '{{ asset('sounds') }}/' + select.value;
                player.play().catch(e => console.error('Preview failed'));
            }
        </script>
    @endpush
@endsection
