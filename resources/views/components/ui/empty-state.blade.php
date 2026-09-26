{{--
  The "nothing here" block of a list: a search that found nothing, a list that is still empty. Every list page uses this one, so the
  icon (40px, slate-300) and the words (13px, slate-600) are the same size everywhere; what changes from page to page is only WHICH
  icon and WHAT it says. The padding belongs to whoever holds it (a table cell, a card, a dialog).

  <x-ui.empty-state>ไม่พบผู้ใช้ตามเงื่อนไขที่เลือก</x-ui.empty-state>
  <x-ui.empty-state icon="search_off" hint="เพิ่มเติม">ไม่พบรายการ</x-ui.empty-state>
  <x-ui.empty-state icon="search_off">ไม่พบรายการ <x-slot:action><a href="…">ล้างค่าทั้งหมด</a></x-slot:action></x-ui.empty-state>

  What is NOT an empty state, and keeps its own look: a note in a single cell or field ("ยังไม่ได้ประเมิน", "ยังไม่ระบุ",
  "ยังไม่ได้มอบหมาย") - it is a value, so it takes the size of the values beside it (12px slate-400 in a table cell) - and a warning
  that asks for an action (the asset page's "repairing, but no request"). A dashed box around the block (a file drop area) is the
  container's, and matches the panels next to it.

  icon    a Material Symbols name; without one, the document icon the list pages have always had
  hint    a second, smaller line under the words
  action  a link or button under them (say "clear the filters")
--}}
@props(['icon' => null, 'hint' => null, 'action' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-2 text-center text-slate-600']) }}>
    @if ($icon)
        <span class="material-symbols-outlined inline-flex h-10 w-10 items-center justify-center text-[40px] leading-none text-slate-300" aria-hidden="true">{{ $icon }}</span>
    @else
        <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
    @endif

    <p class="text-[13px]">{{ $slot }}</p>

    @if ($hint)
        <p class="-mt-1 text-[12px] text-slate-500">{{ $hint }}</p>
    @endif

    @if ($action)
        {{ $action }}
    @endif
</div>
