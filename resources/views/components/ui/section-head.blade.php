{{--
  Numbered heading of a form section (green dot + accent bar + title + hint). Put it as the first child of a <section>.

  <x-ui.section-head no="1" title="ข้อมูลหลัก" subtitle="ทรัพย์สิน / หน่วยงาน / สถานที่" />
  no       omit for an un-numbered heading
--}}
@props([
    'no' => null,
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 pb-3 min-h-[56px]']) }}>
    @if ($no !== null)
        <div class="w-8 h-8 shrink-0 rounded-full border border-emerald-600 bg-emerald-600 flex items-center justify-center text-sm font-bold text-white leading-none">{{ $no }}</div>
    @endif
    <div class="min-w-0 relative pl-3 pt-[1px]">
        <span class="absolute left-0 top-[2px] w-[3px] h-9 rounded-full bg-emerald-600/90"></span>
        <div class="text-base font-semibold text-slate-900 leading-tight">{{ $title }}</div>
        @if ($subtitle)
            <div class="text-sm text-slate-500 leading-snug">{{ $subtitle }}</div>
        @endif
    </div>
</div>
