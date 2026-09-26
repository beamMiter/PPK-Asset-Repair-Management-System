{{--
  Five stars, filled up to the (rounded) score. The star path was copied into four pages; this is the one copy.

  <x-rating.stars :score="4.2" />                 size sm (16px), the default
  <x-rating.stars :score="$r->score" size="xs" />  xs 12px, sm 16px, md 20px, lg 24px

  Amber, the colour of the "rate" button and of the rating dialog. Extra classes (mt-1, shrink-0) are passed through.
--}}
@props(['score' => 0, 'size' => 'sm'])

@php
    $filled = (int) round((float) $score);
    $box = ['xs' => 'h-3 w-3', 'sm' => 'h-4 w-4', 'md' => 'h-5 w-5', 'lg' => 'h-6 w-6'][$size] ?? 'h-4 w-4';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-[2px]']) }} role="img"
    aria-label="{{ number_format((float) $score, 1) }} จาก 5 ดาว">
    @for ($i = 1; $i <= 5; $i++)
        <svg class="{{ $box }} shrink-0 {{ $i <= $filled ? 'text-amber-400' : 'text-slate-200' }}" viewBox="0 0 20 20"
            fill="currentColor" aria-hidden="true">
            <path
                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
    @endfor
</span>
