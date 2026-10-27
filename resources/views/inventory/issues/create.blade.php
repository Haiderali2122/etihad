<x-layouts.app :title="__('Move out of inventory')">
    <x-page-head :title="__('Move out of inventory')" :subtitle="__('Record items leaving the store: ink to a machine, glue to the binders, plates to the press.')" />

    @if ($categories->isEmpty())
        <div class="card"><div class="empty"><b>{{ __('Nothing in stock to move out') }}</b>{{ __('Record a purchase first.') }}</div></div>
    @else
        <form method="POST" action="{{ route('inventory-issues.store') }}" class="card form-card">
            @csrf
            <div class="card-b"><div class="form">
                <div @class(['field', 'full', 'has-err' => $errors->has('inventory_item_id')])>
                    <label for="f_inventory_item_id">{{ __('Item') }} <span class="muted">*</span></label>
                    <select id="f_inventory_item_id" name="inventory_item_id" required>
                        <option value="">{{ __('Choose item') }}</option>
                        @foreach ($categories as $category)
                            <optgroup label="{{ $category->localName() }}">
                                @foreach ($category->items as $item)
                                    <option value="{{ $item->id }}" data-balance="{{ $item->balance() }}" data-unit="{{ $item->unit }}" @selected((string) old('inventory_item_id', $itemId) === (string) $item->id) @disabled($item->balance() <= 0)>
                                        {{ $item->localName() }} — {{ \App\Models\InventoryItem::qty($item->balance()) }} {{ $item->unit }} {{ __('in stock') }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('inventory_item_id')<span class="err">{{ $message }}</span>@enderror
                </div>
                <x-field name="quantity" type="number" step="0.01" min="0.01" :label="__('Quantity going out')" required />
                <div class="field"><label>&nbsp;</label><div id="stockcheck"></div></div>
                <x-field name="issued_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                <x-field name="issued_to" :label="__('Given to')" required list="issueTargets" :hint="__('Person, machine or department')" />
                <datalist id="issueTargets">@foreach ($machines as $m)<option value="{{ __('Machine') }} {{ $m->code }}">@endforeach</datalist>
                <x-field name="machine_id" :label="__('For machine (optional)')" :options="$machines->mapWithKeys(fn ($m) => [$m->id => $m->code.' '.$m->name])" :placeholder="__('Not for a machine')" />
                <x-field name="reference" :label="__('Work order / reference')" placeholder="WO-1042" />
                <x-field name="note" :label="__('Note')" full />
            </div></div>
            <div class="form-actions">
                <a class="btn" href="{{ route('inventory.index', ['tab' => 'issues']) }}">{{ __('Cancel') }}</a>
                <button class="btn primary" type="submit"><x-icon name="send" />{{ __('Move out') }}</button>
            </div>
        </form>
        @push('scripts')
            <script>
                (() => {
                    const item = document.getElementById('f_inventory_item_id'), qty = document.getElementById('f_quantity'), check = document.getElementById('stockcheck');
                    const fmt = n => (Math.round(n * 100) / 100).toLocaleString('en-PK');
                    const sync = () => {
                        const opt = item.selectedOptions[0], have = +(opt?.dataset.balance || 0), unit = opt?.dataset.unit || '', need = +qty.value || 0;
                        if (!item.value || !need) { check.innerHTML = ''; return; }
                        check.innerHTML = need <= have
                            ? `<div class="stockcheck ok">${fmt(have - need)} ${unit} {{ __('left after this') }}</div>`
                            : `<div class="stockcheck no">{{ __('Only') }} ${fmt(have)} ${unit} {{ __('in stock') }}</div>`;
                    };
                    item.addEventListener('change', sync);
                    qty.addEventListener('input', sync);
                    sync();
                })();
            </script>
        @endpush
    @endif
</x-layouts.app>
