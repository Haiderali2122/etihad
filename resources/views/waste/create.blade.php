<x-layouts.app :title="__('Record waste')">
    <x-page-head :title="__('Record waste')" />
    <form method="POST" action="{{ route('waste.store') }}" class="card form-card">
        @csrf
        <div class="card-b"><div class="form">
            <x-field name="work_order_id" :label="__('Work order (optional)')" :options="$orders->mapWithKeys(fn ($o) => [$o->id => $o->number.' — '.$o->bookFormat->book->name])" :value="$orderId" :placeholder="__('Not for an order')" />
            <x-field name="stage" :label="__('Stage')" :options="collect($stages)->mapWithKeys(fn ($s) => [$s->value => $s->label()])" required />
            <x-field name="quantity" type="number" step="0.01" min="0.01" :label="__('Quantity')" required />
            <x-field name="unit" :label="__('Unit')" :options="['kg' => 'kg', 'rims' => __('rims'), 'sheets' => __('sheets')]" required />
            <x-field name="responsible" :label="__('Responsible')" :hint="__('Machine, person or contractor')" />
            <x-field name="recorded_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
            <x-field name="disposal" :label="__('Disposal')" :options="collect($disposals)->mapWithKeys(fn ($d) => [$d->value => $d->label()])" required />
            <x-field name="sale_amount" type="number" min="0" :label="__('Sold for (Rs)')" />
            <x-field name="sold_to" :label="__('Sold to')" />
            <x-field name="note" :label="__('Note')" />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('waste.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const d = document.getElementById('f_disposal');
                const sync = () => ['f_sale_amount', 'f_sold_to'].forEach(id => document.getElementById(id).closest('.field').hidden = d.value !== 'sold');
                d.addEventListener('change', sync); sync();
            })();
        </script>
    @endpush
</x-layouts.app>
