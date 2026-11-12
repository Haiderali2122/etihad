<x-layouts.app :title="__('Buy :material', ['material' => $category->localName()])">
    <x-page-head :back="$back" :title="__('Buy :material', ['material' => $category->localName()])" :subtitle="__('Record a :material purchase: who it was bought from, how much and at what rate. It comes off the manager\'s cash and shows in Cash-out.', ['material' => $category->localName()])" />
    <form method="POST" action="{{ route('purchases.store') }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        <input type="hidden" name="inventory_item_id" value="{{ $item->id }}">
        <div class="card-b"><div class="form">
            <x-field name="purchased_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
            <x-field-other name="party_id" :label="__('Supplier')" :options="$parties->pluck('name', 'id')" :placeholder="__('Choose supplier')" required />
            <x-field name="quantity" type="number" step="0.01" min="0.01" :label="__('Quantity (:unit)', ['unit' => __($item->unit)])" required />
            <x-field name="rate" type="number" step="0.01" min="0" :label="__('Rate per :unit (Rs)', ['unit' => __($item->unit)])" required />
            <div class="field"><label>{{ __('Amount') }}</label><div class="calc" style="padding:10px 14px"><div class="big num" id="amountOut" style="font-size:22px">Rs 0</div></div></div>
            <x-field name="bill_no" :label="__('Bill no.')" />
            <x-field name="attachment" type="file" :label="__('Bill photo / PDF')" />
            <x-field name="note" :label="__('Note')" />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $back }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save purchase') }}</button></div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const q = document.getElementById('f_quantity'), r = document.getElementById('f_rate'), out = document.getElementById('amountOut');
                const calc = () => { out.textContent = 'Rs ' + Math.round((+q.value || 0) * (+r.value || 0)).toLocaleString('en-PK'); };
                [q, r].forEach(el => el.addEventListener('input', calc));
                calc();
            })();
        </script>
    @endpush
</x-layouts.app>
