{{--
  The name of a satisfaction level ("ดีมาก" … "ควรปรับปรุง") in its colour. The wording comes from App\Support\RatingLevel, so the
  team board and a person's own page always agree; the colours live here because Tailwind only reads `resources/`.

  <x-rating.level :average="$avg" :count="$reviews" />          coloured text (a table cell)
  <x-rating.level :average="$avg" :count="$reviews" pill />     a small rounded label (a card)

  count  how many ratings the average is made of; 0 gives "ยังไม่มีการประเมิน", never "ควรปรับปรุง" for an average of nothing
--}}
@props(['average' => null, 'count' => 0, 'pill' => false])

@php
    $level = \App\Support\RatingLevel::of($average === null ? null : (float) $average, (int) $count);
    $text = [
        'great' => 'text-emerald-700',
        'good' => 'text-emerald-600',
        'fair' => 'text-amber-600',
        'poor' => 'text-rose-600',
        'none' => 'text-slate-400',
    ][$level['key']];
    $chip = [
        'great' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'good' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'fair' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'poor' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'none' => 'bg-slate-50 text-slate-500 ring-slate-200',
    ][$level['key']];
@endphp

@if ($pill)
    <span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-[8px] py-[2px] text-[11px] font-semibold ring-1 {$chip}"]) }}>{{ $level['label'] }}</span>
@else
    <span {{ $attributes->merge(['class' => "font-semibold {$text}"]) }}>{{ $level['label'] }}</span>
@endif
