@extends('layouts.auth')
@section('title', 'Create account')

@section('content')
<form method="POST" action="{{ route('register') }}" class="space-y-4">
    @csrf

    {{-- Full name --}}
    <div>
        <label for="name" class="block text-sm font-medium text-slate-700">Full name</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
               autocomplete="name"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2
                      focus:border-[#0E2B51] focus:ring-[#0E2B51]" maxlength="255">
        @error('name')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Citizen ID --}}
    <div>
        <label for="citizen_id" class="block text-sm font-medium text-slate-700">CID</label>
        <input id="citizen_id" type="text" name="citizen_id" value="{{ old('citizen_id') }}"
               required maxlength="13"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2
                      focus:border-[#0E2B51] focus:ring-[#0E2B51]">
        @error('citizen_id')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Email (optional) --}}
    <div>
        <label for="email" class="block text-sm font-medium text-slate-700">Email (ไม่บังคับ)</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}"
               autocomplete="email"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2
                      focus:border-[#0E2B51] focus:ring-[#0E2B51]" maxlength="255">
        @error('email')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Password --}}
    <div>
        <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
        <input id="password" type="password" name="password" required
               autocomplete="new-password"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2
                      focus:border-[#0E2B51] focus:ring-[#0E2B51]">
        @error('password')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Confirm Password --}}
    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required
               autocomplete="new-password"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2
                      focus:border-[#0E2B51] focus:ring-[#0E2B51]">
        @error('password_confirmation')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Actions --}}
    <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm">
            <a href="{{ route('login') }}" class="text-[#0E2B51] hover:underline">Already have an account?</a>
        </div>

        <button class="h-11 px-5 rounded-lg bg-[#0E2B51] text-white font-medium
                       hover:opacity-95 focus:ring-2 focus:ring-offset-2 focus:ring-[#0E2B51]">
            Create account
        </button>
    </div>
</form>
@endsection
