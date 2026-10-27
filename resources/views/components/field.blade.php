@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'options' => null,
    'placeholder' => null,
    'hint' => null,
    'full' => false,
    'required' => false,
])
{{--
    A labelled form control. Pass `options` (value => label) for a select, type="textarea" for a textarea.
--}}
@php
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $current = old($name, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div @class(['field', 'full' => $full, 'has-err' => $errors->has($name)])>
    <label for="{{ $id }}">{{ $label }}@if ($required) <span class="muted">*</span>@endif</label>
    @if ($options !== null)
        <select id="{{ $id }}" name="{{ $name }}" @required($required) {{ $attributes }}>
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" placeholder="{{ $placeholder }}" @required($required) {{ $attributes }}>{{ $current }}</textarea>
    @elseif ($type === 'file')
        <input id="{{ $id }}" type="file" name="{{ $name }}" accept="image/*,application/pdf" {{ $attributes }}>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $current }}" placeholder="{{ $placeholder }}" @required($required) {{ $attributes }}>
    @endif
    @if ($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
    @error($name)
        <span class="err">{{ $message }}</span>
    @enderror
</div>
