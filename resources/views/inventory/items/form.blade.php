<x-layouts.app :title="$item->exists ? __('Edit item') : __('Add item')">
    <x-page-head :title="$item->exists ? __('Edit item') : __('Add item')" :subtitle="__('Something the press keeps in stock, with the unit it is counted in.')" />
    <form method="POST" action="{{ $item->exists ? route('inventory-items.update', $item) : route('inventory-items.store') }}" class="card form-card">
        @csrf
        @if ($item->exists) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="inventory_category_id" :label="__('Category')" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->localName()])" :value="$item->inventory_category_id" :placeholder="__('Choose category')" required />
            <x-field name="name" :label="__('Item name')" :value="$item->name" placeholder="Black ink" required />
            <x-field name="name_ur" :label="__('Name in Urdu')" :value="$item->name_ur" dir="rtl" />
            <x-field name="unit" :label="__('Unit')" :value="$item->unit" placeholder="kg" list="unitList" required :hint="__('How it is counted: kg, litre, pcs, tins…')" />
            <datalist id="unitList">@foreach ($units as $unit)<option value="{{ $unit }}">@endforeach</datalist>
            <x-field name="reorder_level" type="number" step="0.01" min="0" :label="__('Reorder level')" :value="$item->exists ? \App\Models\InventoryItem::qty($item->reorder_level) : null" :hint="__('Warn when the balance drops below this.')" />
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))> {{ __('Active') }}</label></div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $item->exists ? route('inventory-items.show', $item) : route('inventory.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save item') }}</button></div>
    </form>
</x-layouts.app>
