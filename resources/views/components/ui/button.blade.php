{{--
  The one button used across the app. Change a size / colour / radius HERE and every page follows.

  <x-ui.button type="submit" variant="primary" icon="send">บันทึก</x-ui.button>
  <x-ui.button :href="route('assets.index')" icon="close">ยกเลิก</x-ui.button>   (href renders an <a>)
  <x-ui.button variant="danger" size="sm" @click="open = false">ลบ</x-ui.button>

  variant  primary   emerald — create / save / confirm (the main action of a page or dialog)
           secondary white + border — back / cancel / secondary actions          (default)
           danger    rose solid — destructive confirm (reject, delete)
           danger-outline  white + rose border — destructive but not the main action
           info      blue solid — accept / resume style workflow steps
           warning   amber solid — pause / on-hold style actions
           neutral   slate solid — cancel-the-job style actions
           brand     navy — technician / staff side and the rating dialog
           ghost     no border — icon buttons such as a dialog close (X)
           ghost-danger  ghost, rose icon — icon-only destructive tool (delete)
           ghost-warning ghost, amber icon — icon-only lock tool (amber = "locked" everywhere in chat)
  size     md        h-11 (44px) — the same height as a form field (.ui-input), so a button beside an input lines up
                     and stays a comfortable tap target. Page + dialog actions.                       (default)
           sm        h-8 — dense spots: table rows, cards, inline helpers
           square    same height as md, square — icon-only next to inputs (attach file, camera)
           icon      h-8 w-8 round — icon-only, small (dialog close X). Icon-only buttons need `aria-label`
           icon-lg   h-10 w-10 round, 24px icon — icon-only tools that should read at a glance (chat thread header)
  icon     Material Symbols name shown before the label
  split    with `icon`: put the icon in a darker block on the left (the form-submit look)

  Anything else (id, x-on:*, data-*, form, disabled, name/value, …) is passed straight through.
  `type` defaults to "button" — say type="submit" explicitly for a submit button.
  Pass LAYOUT classes only (shrink-0, mt-2, hidden) — never height / colour / radius / width, or the sizes drift
  apart again. A button is as wide as its label + padding; don't stretch it (no w-full / flex-1 / min-w-*).

  Padding is written as px-[16px], NOT px-4: the pages also load Bootstrap from a CDN, whose !important
  .px-3 / .px-4 / .px-5 / .gap-3 (1rem / 1.5rem / 3rem / 1rem) beat Tailwind's same-named classes, so px-4 rendered
  as 24px and px-5 as 48px. Arbitrary values have no Bootstrap twin. Keep it that way for anything added here.
--}}
@props([
    'variant' => 'secondary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'split' => false,
])

@php
    $useSplit = $split && $icon;

    $base = 'inline-flex items-center justify-center font-semibold whitespace-nowrap select-none transition-all '
          . 'active:scale-95 focus:outline-none focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100';

    $sizes = [
        'md'     => 'h-11 rounded-md text-[13px]',
        'sm'     => 'h-8 rounded-md text-[12px]',
        'square' => 'h-11 w-11 shrink-0 rounded-md text-[13px]',
        'icon'   => 'h-8 w-8 shrink-0 rounded-full text-[13px]',
        'icon-lg' => 'h-10 w-10 shrink-0 rounded-full text-[13px]',
    ];
    $pads = ['md' => 'px-[16px]', 'sm' => 'px-[12px]', 'square' => '', 'icon' => '', 'icon-lg' => ''];

    $variants = [
        'primary'        => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-200',
        'secondary'      => 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 focus:ring-slate-200',
        'danger'         => 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-200',
        'danger-outline' => 'border border-rose-200 bg-white text-rose-700 hover:bg-rose-50 hover:border-rose-300 focus:ring-rose-100',
        'info'           => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-200',
        'warning'        => 'bg-amber-600 text-white hover:bg-amber-700 focus:ring-amber-200',
        'neutral'        => 'bg-slate-600 text-white hover:bg-slate-700 focus:ring-slate-200',
        'brand'          => 'bg-[#0F2D5C] text-white hover:bg-[#1a3d75] focus:ring-[#0F2D5C]/30',
        'ghost'          => 'text-slate-400 hover:bg-slate-100 hover:text-slate-700 focus:ring-slate-200',
        'ghost-danger'   => 'text-rose-500 hover:bg-rose-50 hover:text-rose-600 focus:ring-rose-100',
        'ghost-warning'  => 'text-amber-600 hover:bg-amber-50 hover:text-amber-700 focus:ring-amber-100',
    ];

    $size = isset($sizes[$size]) ? $size : 'md';
    $iconSize = ['sm' => 'text-[16px]', 'icon-lg' => 'text-[24px]'][$size] ?? 'text-[18px]';

    $classes = implode(' ', array_filter([
        $base,
        $sizes[$size],
        $useSplit ? 'overflow-hidden' : trim($pads[$size] . ' gap-1.5'),
        $variants[$variant] ?? $variants['secondary'],
    ]));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
@endif
    @if ($useSplit)
        <span class="hidden sm:flex h-full items-center justify-center bg-black/10 px-2.5 border-r border-white/10">
            <span class="material-symbols-outlined {{ $iconSize }}" aria-hidden="true">{{ $icon }}</span>
        </span>
        <span class="flex items-center gap-1.5 px-[16px]">
            <span class="sm:hidden material-symbols-outlined {{ $iconSize }}" aria-hidden="true">{{ $icon }}</span>
            {{ $slot }}
        </span>
    @else
        @if ($icon)
            <span class="material-symbols-outlined {{ $iconSize }}" aria-hidden="true">{{ $icon }}</span>
        @endif
        {{ $slot }}
    @endif
@if ($href)
    </a>
@else
    </button>
@endif
