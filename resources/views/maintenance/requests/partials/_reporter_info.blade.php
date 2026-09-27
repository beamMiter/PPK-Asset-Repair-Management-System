<section class="flex flex-col">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 {{ $headCls }}">
        <div class="flex items-start gap-3 min-w-0">
            <div class="{{ $noCls }}">3</div>
            <div class="{{ $accentWrap }}">
                <span class="{{ $accentBar }}"></span>
                <div class="{{ $titleCls }}">ผู้แจ้ง</div>
                <div class="{{ $subCls }}">ระบุข้อมูลและรายละเอียดการติดต่อของผู้แจ้ง</div>
            </div>
        </div>
    </div>

    <div class="flex flex-col space-y-4">
        <div class="flex flex-col">
            <div class="flex items-center justify-between mb-2">
                <div class="text-[14px] font-semibold text-slate-800">ข้อมูลผู้แจ้ง</div>
                <div class="text-[12px] text-slate-500">ผู้แจ้ง</div>
            </div>
            <div class="rounded-lg border {{ $line }} bg-white px-4 py-3 flex items-center gap-4">
                @php
                    $reporter = $req->reporter;
                    $avatar = $reporter ? $reporter->avatar_thumb_url : \App\Support\InitialsAvatar::url($req->reporter_name ?? 'User', 88);
                @endphp
                <div class="h-11 w-11 shrink-0 overflow-hidden rounded-full border {{ $line }} bg-slate-50">
                    <img src="{{ $avatar }}" alt="{{ $req->reporter?->name ?? ($req->reporter_name ?? 'User') }}" class="h-full w-full object-cover">
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-bold text-[15px] text-slate-900 leading-tight">
                        {{ $req->reporter?->name ?? ($req->reporter_name ?? '-') }}
                    </div>
                    @if (($req->reporter?->email ?? $req->reporter_email) || $req->reporter_phone)
                        <div class="mt-1.5 text-[13px] text-slate-500 space-y-1">
                            @if ($req->reporter?->email ?? $req->reporter_email)
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-slate-400">mail</span>
                                    {{ $req->reporter?->email ?? $req->reporter_email }}
                                </div>
                            @endif
                            @if ($req->reporter_phone)
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-slate-400">call</span>
                                    โทร. {{ $req->reporter_phone }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
