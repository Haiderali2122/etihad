@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'full' => false,
    'required' => false,
])
{{--
    A dropdown with an "Other…" choice at the end. Picking it shows a box to type a new name, which is saved
    to the list (see App\Support\OtherOption). Not used for books, parties or paper.
--}}
@php
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $current = old($name, $value instanceof \BackedEnum ? $value->value : $value);
    $isOther = (string) $current === \App\Support\OtherOption::Value;
@endphp
<div @class(['field', 'full' => $full, 'has-err' => $errors->has($name)]) data-other-field>
    <label for="{{ $id }}">{{ $label }}@if ($required) <span class="muted">*</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}" @required($required) {{ $attributes }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
        <option value="{{ \App\Support\OtherOption::Value }}" @selected($isOther)>{{ __('Other… (type a new one)') }}</option>
    </select>
    <input type="text" name="{{ $name }}_other" value="{{ old($name.'_other') }}" maxlength="80" placeholder="{{ __('Type the name') }}" aria-label="{{ __(':label (new)', ['label' => $label]) }}" @if (! $isOther) hidden @endif style="margin-top:6px">
    @if ($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
    @error($name)
        <span class="err">{{ $message }}</span>
    @enderror
</div>
@once
    @push('scripts')
        <script>
            document.addEventListener('change', (e) => {
                const select = e.target.closest('[data-other-field] select');
                if (!select) { return; }
                const box = select.closest('[data-other-field]').querySelector('input[type="text"]');
                const other = select.value === @js(\App\Support\OtherOption::Value);
                box.hidden = !other;
                box.required = other;
                if (other) { box.focus(); }
            });
        </script>
    @endpush
@endonce
