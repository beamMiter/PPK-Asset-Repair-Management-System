@extends('layouts.app')
@section('title', 'Edit Profile')

@section('content')
    @php
        $avatarMain = data_get($user, 'avatar_url');
        $avatarThumb = data_get($user, 'avatar_thumb_url');

        $name = trim((string) ($user->name ?? ''));
        $parts = preg_split('/\s+/u', $name) ?: [];
        $initials = strtoupper(mb_substr($parts[0] ?? 'U', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));

        // ดึงค่า Code แผนกจาก old input หรือจาก database
        $currentValue = old('department', $user->department);
    @endphp

    <div class="w-full flex flex-col min-h-screen bg-white">

        {{-- Header Section --}}
        <div class="sticky top-16 z-20 bg-white border-b border-slate-200">
            <div class="px-4 md:px-6 lg:px-8 py-5">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-[32px] text-[#0F2D5C] mt-0.5">manage_accounts</span>
                        <div>
                            <h1 class="text-[18px] font-semibold text-slate-900 leading-none">Edit Profile</h1>
                            <p class="mt-1.5 text-[13px] text-slate-600">แก้ไขข้อมูลส่วนตัวของคุณ</p>
                        </div>
                    </div>
                    <x-ui.back-button :fallback="route('profile.show')" />
                </div>
            </div>
        </div>

        <div class="px-4 md:px-6 lg:px-8 py-10 max-w-4xl mx-auto w-full">
            @if (session('status'))
                <div
                    class="mb-8 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-[13px] text-emerald-700 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                <div class="space-y-0">
                    {{-- ส่วนรูปโปรไฟล์ --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 py-8 border-b border-slate-100 gap-6">
                        <div>
                            <h3 class="text-[15px] font-bold text-slate-800">รูปโปรไฟล์</h3>
                            <p class="text-[12px] text-slate-500 mt-1">คลิกที่ปุ่มเพื่อเปลี่ยนรูป</p>
                        </div>
                        <div class="md:col-span-2 flex items-center gap-6">
                            <div class="relative">
                                <img id="avatar-preview"
                                    @if (!$avatarThumb && !$avatarMain) class="hidden h-20 w-20 rounded-full object-cover border-2 border-slate-100 "
                                    @else class="h-20 w-20 rounded-full object-cover border-2 border-slate-100 " @endif
                                    src="{{ $avatarThumb ?: $avatarMain }}" />

                                <div id="avatar-fallback" @if ($avatarThumb || $avatarMain) class="hidden" @endif
                                    class="flex h-20 w-20 items-center justify-center rounded-full bg-emerald-600 text-white border border-emerald-700/10">
                                    <span class="text-2xl font-bold tracking-tighter">{{ $initials }}</span>
                                </div>
                            </div>

                            <div class="flex-1 space-y-4">
                                <div class="flex flex-col gap-2">
                                    <label for="avatar-input"
                                        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-slate-100 text-slate-700 rounded-lg border border-slate-300 text-[13px] font-bold cursor-pointer hover:bg-slate-200 transition-all w-fit">
                                        <span class="material-symbols-outlined text-[18px]">cloud_upload</span>
                                        เลือกไฟล์รูปภาพใหม่
                                    </label>
                                    <input id="avatar-input" type="file" name="avatar" accept="image/*" class="hidden">
                                    <p id="file-name-display" class="text-[12px] text-slate-400 italic">
                                        ยังไม่ได้เลือกไฟล์ใหม่...</p>
                                </div>

                                <label class="inline-flex items-center gap-2 text-[13px] text-slate-600 cursor-pointer">
                                    <input id="remove-avatar" type="checkbox" name="remove_avatar" value="1"
                                        class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
                                    ลบรูปโปรไฟล์ปัจจุบัน
                                </label>
                                @error('avatar')
                                    <p class="text-[12px] text-rose-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- เลขบัตร --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 py-6 border-b border-slate-100 gap-4 items-start">
                        <div class="text-[14px] font-bold text-slate-500 uppercase tracking-wide mt-2.5">เลขประจำตัวประชาชน
                        </div>
                        <div class="md:col-span-2">
                            <input type="text" value="{{ $user->citizen_id }}"
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg text-[15px] text-slate-400 font-mono py-2.5 px-3 cursor-not-allowed"
                                readonly>
                            <p class="mt-2 text-[12px] text-amber-600 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">info</span>
                                ไม่สามารถแก้ไขได้ หากข้อมูลผิดพลาดกรุณาติดต่อเจ้าหน้าที่ดูแลระบบ
                            </p>
                        </div>
                    </div>

                    {{-- ชื่อ --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 py-6 border-b border-slate-100 gap-4 items-center">
                        <label for="name" class="text-[14px] font-bold text-slate-500 uppercase tracking-wide">ชื่อ -
                            นามสกุล</label>
                        <div class="md:col-span-2">
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                                required
                                class="w-full rounded-lg border-slate-200 text-[15px] py-2.5 focus:ring-emerald-600 focus:border-emerald-600 @error('name') border-rose-400 @enderror" maxlength="255">
                            @error('name')
                                <p class="mt-1 text-[12px] text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- อีเมล --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 py-6 border-b border-slate-100 gap-4 items-center">
                        <label for="email"
                            class="text-[14px] font-bold text-slate-500 uppercase tracking-wide">อีเมล</label>
                        <div class="md:col-span-2">
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
                                class="w-full rounded-lg border-slate-200 text-[15px] py-2.5 focus:ring-emerald-600 focus:border-emerald-600 @error('email') border-rose-400 @enderror" maxlength="255">
                        </div>
                    </div>

                    {{-- หน่วยงาน (ปรับกล่องให้เหมือนชาวบ้าน) --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 py-6 border-b border-slate-100 gap-4 items-center">
                        <label for="department"
                            class="text-[14px] font-bold text-slate-500 uppercase tracking-wide">หน่วยงาน / แผนก</label>
                        <div class="md:col-span-2">
                            <select id="department" name="department"
                                class="w-full rounded-lg border-slate-200 text-[15px] py-2.5 focus:ring-emerald-600 focus:border-emerald-600 appearance-none bg-white">
                                <option value="">— ไม่ระบุหน่วยงาน —</option>
                                @foreach (\App\Models\Department::query()->orderBy('code')->get() as $dept)
                                    <option value="{{ $dept->code }}" @selected($currentValue == $dept->code)>
                                        {{ $dept->code }} — {{ $dept->display_name ?? $dept->name_th }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-[12px] text-slate-400 italic">เลือกหน่วยงานที่คุณสังกัดปัจจุบัน</p>
                        </div>
                    </div>

                    <div class="py-10 flex justify-end gap-3">
                        <x-ui.button type="submit" variant="primary" icon="check_circle" split>บันทึกการเปลี่ยนแปลง</x-ui.button>
                    </div>
                </div>
            </form>

            {{-- เปลี่ยนรหัสผ่าน — its own form (PUT /password): the routes and the controller were there, the page for them was not, so
                 nobody could change the password they were first given --}}
            @php $passwordErrors = $errors->updatePassword; @endphp
            <form id="password-form" method="POST" action="{{ route('password.update') }}" class="border-t border-slate-200"
                data-dirty-check="true">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-3 py-8 gap-6">
                    <div>
                        <h3 class="text-[15px] font-bold text-slate-800">เปลี่ยนรหัสผ่าน</h3>
                        <p class="text-[12px] text-slate-500 mt-1">อย่างน้อย 8 ตัวอักษร ต้องมีทั้งตัวอักษรและตัวเลข เมื่อเปลี่ยนแล้ว
                            อุปกรณ์เครื่องอื่นที่เข้าสู่ระบบอยู่จะถูกออกจากระบบ</p>
                    </div>

                    <div class="md:col-span-2 space-y-4">
                        <div>
                            <label for="current_password" class="block text-[13px] font-medium text-slate-600">รหัสผ่านปัจจุบัน</label>
                            <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                                class="mt-1 w-full rounded-lg border-slate-200 text-[15px] py-2.5 focus:ring-emerald-600 focus:border-emerald-600 @if ($passwordErrors->has('current_password')) border-rose-400 @endif">
                            @if ($passwordErrors->has('current_password'))
                                <p class="mt-1 text-[12px] text-rose-600">{{ $passwordErrors->first('current_password') }}</p>
                            @endif
                        </div>

                        <div>
                            <label for="new_password" class="block text-[13px] font-medium text-slate-600">รหัสผ่านใหม่</label>
                            <input id="new_password" name="password" type="password" required autocomplete="new-password"
                                class="mt-1 w-full rounded-lg border-slate-200 text-[15px] py-2.5 focus:ring-emerald-600 focus:border-emerald-600 @if ($passwordErrors->has('password')) border-rose-400 @endif">
                            @if ($passwordErrors->has('password'))
                                <p class="mt-1 text-[12px] text-rose-600">{{ $passwordErrors->first('password') }}</p>
                            @endif
                        </div>

                        <div>
                            <label for="new_password_confirmation" class="block text-[13px] font-medium text-slate-600">ยืนยันรหัสผ่านใหม่</label>
                            <input id="new_password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                                class="mt-1 w-full rounded-lg border-slate-200 text-[15px] py-2.5 focus:ring-emerald-600 focus:border-emerald-600">
                        </div>

                        <div class="flex justify-end pt-2">
                            <x-ui.button type="submit" variant="primary" icon="lock_reset">เปลี่ยนรหัสผ่าน</x-ui.button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Cropper (คงเดิม) --}}
    <div id="cropper-modal" class="fixed inset-0 z-[100] hidden overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            <div class="relative w-full max-w-xl rounded-md bg-white overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-900">ตัดรูปโปรไฟล์</h3>
                    <button type="button" id="cropper-close" class="text-slate-400 hover:text-slate-600">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6">
                    <div class="aspect-square w-full bg-slate-50 rounded-xl overflow-hidden border border-slate-100">
                        <img id="cropper-image" alt="To crop" class="max-w-full block">
                    </div>
                </div>
                <div class="px-6 py-4 bg-slate-50 flex items-center justify-end gap-3">
                    <x-ui.button id="cropper-cancel">ยกเลิก</x-ui.button>
                    <x-ui.button id="cropper-apply" variant="primary">ตกลง</x-ui.button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link href="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js"></script>
    <script>
        (() => {
            const fileInput = document.getElementById('avatar-input');
            const fileNameDisplay = document.getElementById('file-name-display');
            const previewEl = document.getElementById('avatar-preview');
            const fallbackEl = document.getElementById('avatar-fallback');
            const removeBox = document.getElementById('remove-avatar');
            const modal = document.getElementById('cropper-modal');
            const imgEl = document.getElementById('cropper-image');
            const btnClose = document.getElementById('cropper-close');
            const btnCancel = document.getElementById('cropper-cancel');
            const btnApply = document.getElementById('cropper-apply');

            let cropper = null;
            let pendingFileName = null;

            function openModal() {
                modal.classList.remove('hidden');
            }

            function closeModal() {
                modal.classList.add('hidden');
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
            }

            fileInput.addEventListener('change', (e) => {
                const [file] = e.target.files || [];
                if (!file) return;
                fileNameDisplay.innerText = file.name;
                if (removeBox) removeBox.checked = false;
                pendingFileName = file.name;
                const reader = new FileReader();
                reader.onload = () => {
                    imgEl.src = reader.result;
                    openModal();
                    imgEl.onload = () => {
                        cropper = new Cropper(imgEl, {
                            aspectRatio: 1,
                            viewMode: 1,
                            dragMode: 'move',
                            autoCropArea: 1,
                            background: false,
                        });
                    };
                };
                reader.readAsDataURL(file);
            });

            btnClose.addEventListener('click', closeModal);
            btnCancel.addEventListener('click', () => {
                fileInput.value = '';
                fileNameDisplay.innerText = 'ยังไม่ได้เลือกไฟล์ใหม่...';
                closeModal();
            });

            btnApply.addEventListener('click', () => {
                if (!cropper) return;
                const canvas = cropper.getCroppedCanvas({
                    width: 512,
                    height: 512
                });
                canvas.toBlob((blob) => {
                    const fileName = (pendingFileName || 'avatar').split('.')[0] + '.webp';
                    const croppedFile = new File([blob], fileName, {
                        type: 'image/webp'
                    });
                    const dt = new DataTransfer();
                    dt.items.add(croppedFile);
                    fileInput.files = dt.files;
                    const previewReader = new FileReader();
                    previewReader.onload = () => {
                        previewEl.src = previewReader.result;
                        previewEl.classList.remove('hidden');
                        if (fallbackEl) fallbackEl.classList.add('hidden');
                    };
                    previewReader.readAsDataURL(croppedFile);
                    closeModal();
                }, 'image/webp', 0.85);
            });

            if (removeBox) {
                removeBox.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        fileInput.value = '';
                        fileNameDisplay.innerText = 'รูปจะถูกลบเมื่อบันทึก...';
                        previewEl.classList.add('hidden');
                        if (fallbackEl) fallbackEl.classList.remove('hidden');
                    }
                });
            }
        })();
    </script>
@endpush
