{{--
  Bottom action row of a create / edit form: [ยกเลิก] [บันทึก], right-aligned. Both are the same height and
  only as wide as their labels (wraps onto a second line on a very narrow screen instead of stretching).

  <x-ui.form-actions :cancel-href="route('assets.index')" submit-label="บันทึกข้อมูล" />
  <x-ui.form-actions :cancel-href="..." submit-label="ส่งใบแจ้งซ่อมบำรุง" submit-icon="check" form="main-form" />

  cancel-href   omit to render only the submit button
  form          id of the <form> when this row sits outside it (adds form="…" to the submit button)
  Spacing (pt-6 mt-6 + top border) is the standard — don't pass mt-*/pt-*. Other layout classes are merged.
--}}
@props([
    'cancelHref' => null,
    'cancelLabel' => 'ยกเลิก',
    'submitLabel' => 'บันทึกข้อมูล',
    'submitIcon' => 'save',
    'form' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-wrap justify-end gap-[12px] pt-6 mt-6 border-t border-slate-200']) }}>
    @if ($cancelHref)
        <x-ui.button :href="$cancelHref" icon="close">{{ $cancelLabel }}</x-ui.button>
    @endif

    <x-ui.button type="submit" variant="primary" :icon="$submitIcon" split :form="$form">
        {{ $submitLabel }}
    </x-ui.button>
</div>
