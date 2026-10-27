<x-layouts.app :title="__('Add purchase')">
    <x-page-head :title="__('Add purchase')" :subtitle="__('Stock in: attach a photo of the bill. Amount is worked out from quantity × rate.')">
        @can('inventory.manage')
            <x-slot:actions><a class="btn" href="{{ route('inventory-items.create') }}"><x-icon name="plus" />{{ __('New item') }}</a></x-slot:actions>
        @endcan
    </x-page-head>

    @if ($categories->isEmpty())
        <div class="card"><div class="empty"><b>{{ __('No items yet') }}</b>{{ __('Add an item first, then record its purchase.') }}</div></div>
    @else
        <form method="POST" action="{{ route('purchases.store') }}" enctype="multipart/form-data" class="card form-card">
            @csrf
            <div class="card-b"><div class="form">
                <x-field name="purchased_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                <x-field name="party_id" :label="__('Supplier')" :options="$parties->pluck('name', 'id')" :placeholder="__('Choose supplier')" required />
                <div @class(['field', 'full', 'has-err' => $errors->has('inventory_item_id')])>
                    <label for="f_inventory_item_id">{{ __('Item') }} <span class="muted">*</span></label>
                    <select id="f_inventory_item_id" name="inventory_item_id" required>
                        <option value="">{{ __('Choose item') }}</option>
                        @foreach ($categories as $category)
                            <optgroup label="{{ $category->localName() }}">
                                @foreach ($category->items as $item)
                                    <option value="{{ $item->id }}" data-unit="{{ $item->unit }}" @selected((string) old('inventory_item_id', $itemId) === (string) $item->id)>{{ $item->localName() }} ({{ $item->unit }})</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('inventory_item_id')<span class="err">{{ $message }}</span>@enderror
                </div>
                <x-field name="quantity" type="number" step="0.01" min="0" :label="__('Quantity')" required />
                <x-field name="rate" type="number" step="0.01" min="0" :label="__('Rate (Rs)')" required />
                <div class="field"><label>{{ __('Amount') }}</label><div class="calc" style="padding:10px 14px"><div class="big num" id="amountOut" style="font-size:22px">Rs 0</div></div></div>
                <x-field name="bill_no" :label="__('Bill no.')" />
                <x-field name="attachment" type="file" :label="__('Bill photo / PDF')" />
                <x-field name="note" :label="__('Note')" />
            </div></div>
            <div class="form-actions"><a class="btn" href="{{ route('inventory.index', ['tab' => 'purchases']) }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save purchase') }}</button></div>
        </form>
        @push('scripts')
            <script>
                (() => {
                    const item = document.getElementById('f_inventory_item_id'), q = document.getElementById('f_quantity'), r = document.getElementById('f_rate'), out = document.getElementById('amountOut');
                    const qtyLabel = document.querySelector('label[for="f_quantity"]'), base = qtyLabel.firstChild.textContent.trim();
                    const calc = () => {
                        out.textContent = 'Rs ' + Math.round((+q.value || 0) * (+r.value || 0)).toLocaleString('en-PK');
                        const unit = item.selectedOptions[0]?.dataset.unit;
                        qtyLabel.firstChild.textContent = (unit ? `${base} (${unit})` : base) + ' ';
                    };
                    [q, r].forEach(el => el.addEventListener('input', calc));
                    item.addEventListener('change', calc);
                    calc();
                })();
            </script>
        @endpush
    @endif
</x-layouts.app>
