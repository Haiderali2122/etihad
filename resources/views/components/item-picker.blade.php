@props([
    'items',
    'selected' => null,
    'label' => null,
    'stock' => false,
])
{{--
    The Item box: type the item's name or pick it from the list (no "Other…" step). The chosen item's id goes in a
    hidden field that carries its unit, stock and machine-part flag for the form's own script.
--}}
@php
    $labels = \App\Support\ItemPicker::labels($items);
    $selectedId = old('inventory_item_id', $selected);
    $typed = old('item_name', collect($labels)->search(fn ($i) => (string) $i->id === (string) $selectedId) ?: '');
    $data = collect($labels)->map(fn ($i) => ['id' => $i->id, 'unit' => $i->unit, 'balance' => $stock ? $i->balance() : null, 'machines' => (bool) $i->category?->for_machines]);
@endphp
<div @class(['field', 'full', 'has-err' => $errors->has('inventory_item_id') || $errors->has('item_name')])>
    <label for="f_item_name">{{ $label ?? __('Item') }} <span class="muted">*</span></label>
    <input type="text" id="f_item_name" name="item_name" value="{{ $typed }}" list="itemPickerList" autocomplete="off" maxlength="120" required>
    <input type="hidden" id="f_inventory_item_id" name="inventory_item_id" value="{{ $selectedId }}">
    <datalist id="itemPickerList">
        @foreach ($labels as $text => $item)
            <option value="{{ $text }}">{{ $stock ? \App\Models\InventoryItem::qty($item->balance()).' '.$item->unit.' '.__('in stock') : $item->category?->localName() }}</option>
        @endforeach
    </datalist>
    @error('inventory_item_id')<span class="err">{{ $message }}</span>@enderror
    @error('item_name')<span class="err">{{ $message }}</span>@enderror
</div>
@push('scripts')
    <script>
        (() => {
            const items = @js($data), box = document.getElementById('f_item_name'), id = document.getElementById('f_inventory_item_id');
            const norm = s => s.trim().replace(/\s+/g, ' ').toLowerCase();
            const pick = () => {
                const key = Object.keys(items).find(k => norm(k) === norm(box.value));
                const it = key ? items[key] : null;
                id.value = it ? it.id : '';
                id.dataset.unit = it ? it.unit : '';
                id.dataset.balance = it && it.balance !== null ? it.balance : '';
                id.dataset.machines = it && it.machines ? '1' : '0';
                id.dispatchEvent(new Event('change'));
            };
            box.addEventListener('input', pick);
            pick();
        })();
    </script>
@endpush
