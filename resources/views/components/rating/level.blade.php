{{--
  The name of a satisfaction level ("ดีมาก" … "ควรปรับปรุง") in its colour — plain coloured text, no box: that is how every other
  list of the app writes a status (the request list, the asset list), and a boxed label here would be the only one.
  The wording comes from App\Support\RatingLevel, so the team board and a person's own page always agree; the colours live here
  because Tailwind only reads `resources/`.

  <x-rating.level :average="$avg" :count="$reviews" />
  <x-rating.level :average="$avg" :count="$reviews" class="text-[12px]" />

  count  how many ratings the average is made of; 0 gives "ยังไม่มีการประเมิน", never "ควรปรับปรุง" for an average of nothing
--}}
@props(['average' => null, 'count' => 0])

@php
    $level = \App\Support\RatingLevel::of($average === null ? null : (float) $average, (int) $count);
    $text = [
        'great' => 'text-emerald-700',
        'good' => 'text-emerald-600',
        'fair' => 'text-amber-600',
        'poor' => 'text-rose-600',
        'none' => 'text-slate-400',
    ][$level['key']];
@endphp

<span {{ $attributes->merge(['class' => "font-semibold {$text}"]) }}>{{ $level['label'] }}</span>
