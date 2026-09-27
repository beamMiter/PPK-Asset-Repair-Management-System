{{--
  Numbered heading of a form section (green dot + accent bar + title + hint). Put it as the first child of a <section>.

  <x-ui.section-head no="1" title="ข้อมูลหลัก" subtitle="ทรัพย์สิน / หน่วยงาน / สถานที่" />
  no       omit for an un-numbered heading

  actions  optional slot: icon tools for the section, at the right-hand end of the heading (top right). Where a file can be added
           the paperclip and camera go here, on every page, as the assign-team icon does on the job page:

           <x-ui.section-head no="4" title="ไฟล์แนบ" subtitle="...">
               <x-slot:actions><x-ui.attach-buttons any="…" camera="…" /></x-slot:actions>
           </x-ui.section-head>

           An empty slot (e.g. only an @if that shows nothing) leaves no gap. The job page builds the same heading by hand
           (partials/_attachments, _assigned_team); keep the two the same.
--}}
@props([
    'no' => null,
    'title',
    'subtitle' => null,
    'actions' => null,
])

<div {{ $attributes->merge(['class' => 'flex items-start justify-between gap-4 pb-3 min-h-[56px]']) }}>
    <div class="flex items-start gap-3 min-w-0">
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

    {{-- -mt-1 puts the centre of the 40px icon on the centre of the 32px number circle --}}
    @if ($actions && $actions->hasActualContent())
        <div class="flex items-center gap-1 shrink-0 -mt-1">{{ $actions }}</div>
    @endif
</div>
