{{--
  "กลับ" button for page headers. Goes to the page the user came from; falls back to $fallback when the
  previous URL is this very page (e.g. after a validation redirect) so it never links to itself.

  <x-ui.back-button :fallback="route('assets.index')" />
--}}
@props(['fallback'])

@php
    $previous = url()->previous();
    $target = $previous !== url()->current() ? $previous : $fallback;
@endphp

<x-ui.button :href="$target" icon="chevron_left" {{ $attributes }}>
    {{ $slot->isEmpty() ? 'กลับ' : $slot }}
</x-ui.button>
