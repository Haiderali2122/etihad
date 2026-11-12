@props([
    'parties',
    'selected' => null,
])
{{--
    The Supplier box: type the name or pick it from the list (no placeholder, no "Other…" step). A new name is
    added to Parties as a supplier; its phone and other details can be filled in there later.
--}}
@php
    $typed = old('supplier_name', $parties->firstWhere('id', $selected)?->name ?? '');
@endphp
<div @class(['field', 'has-err' => $errors->has('party_id') || $errors->has('supplier_name')])>
    <label for="f_supplier_name">{{ __('Supplier') }} <span class="muted">*</span></label>
    <input type="text" id="f_supplier_name" name="supplier_name" value="{{ $typed }}" list="supplierPickerList" autocomplete="off" maxlength="120" required>
    <datalist id="supplierPickerList">@foreach ($parties as $p)<option value="{{ $p->name }}">@endforeach</datalist>
    @error('party_id')<span class="err">{{ $message }}</span>@enderror
    @error('supplier_name')<span class="err">{{ $message }}</span>@enderror
</div>
