<x-layouts.app :title="__('Log repair')">
    <x-page-head :title="__('Log repair')" :subtitle="__('Record the part, the vendor, the bill and any discount. Attach the invoice photo.')" />
    <form method="POST" action="{{ route('machine-repairs.store') }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        <div class="card-b"><div class="form">
            <x-field name="machine_id" :label="__('Machine')" :options="$machines->mapWithKeys(fn ($m) => [$m->id => $m->code.' '.$m->name])" :value="$machineId" :placeholder="__('Choose machine')" required />
            <x-field name="repaired_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
            <x-field name="kind" :label="__('Repair or new part')" :options="collect($kinds)->mapWithKeys(fn ($k) => [$k->value => $k->label()])" required />
            <x-field name="part_id" :label="__('Part')" :options="$parts->mapWithKeys(fn ($p) => [$p->id => $p->name.($p->name_ur ? ' / '.$p->name_ur : '')])" :placeholder="__('No specific part')" />
            <x-field name="description" :label="__('Work done')" placeholder="Roller re-rubbered" full required />
            <x-field name="party_id" :label="__('Mechanic / vendor')" :options="$vendors->pluck('name', 'id')" :placeholder="__('Choose vendor')" required />
            <x-field name="invoice_no" :label="__('Invoice no.')" />
            <x-field name="bill_amount" type="number" min="0" :label="__('Bill (Rs)')" required />
            <x-field name="discount" type="number" min="0" :label="__('Discount (Rs)')" value="0" />
            <x-field name="amount_paid" type="number" min="0" :label="__('Amount paid (Rs)')" required :hint="__('Bill minus discount, plus any loading or extra charges')" />
            <x-field name="attachment" type="file" :label="__('Invoice photo')" />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('machines.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save repair') }}</button></div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const bill = document.getElementById('f_bill_amount'), disc = document.getElementById('f_discount'), paid = document.getElementById('f_amount_paid');
                let touched = !!paid.value;
                paid.addEventListener('input', () => touched = true);
                const calc = () => { if (!touched) { paid.value = Math.max(0, (+bill.value || 0) - (+disc.value || 0)); } };
                bill.addEventListener('input', calc); disc.addEventListener('input', calc);
            })();
        </script>
    @endpush
</x-layouts.app>
