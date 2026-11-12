<x-layouts.app :title="__('Move out of inventory')">
    <x-page-head :back="route('inventory.index', ['tab' => 'issues'])" :title="__('Move out of inventory')" :subtitle="__('Record items leaving the store: ink to a machine, glue to the binders, plates to the press. At the end of the shift, enter what came back on the Moved out tab.')" />

    @if ($categories->isEmpty())
        <div class="card"><div class="empty"><b>{{ __('Nothing in stock to move out') }}</b>{{ __('Record a purchase first.') }}</div></div>
    @else
        <form method="POST" action="{{ route('inventory-issues.store') }}" class="card form-card">
            @csrf
            <div class="card-b"><div class="form">
                <x-item-picker :items="$categories->flatMap(fn ($c) => $c->items->each->setRelation('category', $c))->filter(fn ($i) => $i->balance() > 0)->values()" :selected="$itemId" stock />
                <x-field name="quantity" type="number" step="0.01" min="0.01" :label="__('Quantity going out')" required />
                <div class="field"><label>&nbsp;</label><div id="stockcheck"></div></div>
                <x-field name="issued_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                <x-field name="party_id" :label="__('Given to kharadia')" :options="$kharadias->pluck('name', 'id')" :placeholder="__('Choose kharadia')" :hint="$kharadias->isEmpty() ? __('Add kharadias under Parties with the type Kharadia.') : null" />
                <x-field name="employee_id" :label="__('Given to employee')" :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->name.($e->designation ? ' · '.$e->designation : '')])" :placeholder="__('Not to an employee')" />
                <x-field name="machine_id" :label="__('For machine (optional)')" :options="$machines->mapWithKeys(fn ($m) => [$m->id => $m->code.' '.$m->name])" :placeholder="__('Not for a machine')" />
                <x-field name="issued_to" :label="__('Given to')" list="issueTargets" :hint="__('Department or person not on the staff list. Leave empty to use the employee\'s name.')" />
                <datalist id="issueTargets">@foreach ($machines as $m)<option value="{{ __('Machine') }} {{ $m->code }}">@endforeach</datalist>
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
                        const have = +(item.dataset.balance || 0), unit = item.dataset.unit || '', need = +qty.value || 0;
                        if (!item.value || !need) { check.innerHTML = ''; return; }
                        check.innerHTML = need <= have
                            ? `<div class="stockcheck ok">${fmt(have - need)} ${unit} {{ __('left after this') }}</div>`
                            : `<div class="stockcheck no">{{ __('Only') }} ${fmt(have)} ${unit} {{ __('in stock') }}</div>`;
                    };
                    // Machine parts go to a kharadia; everything else to an employee.
                    const who = () => {
                        const machines = item.dataset.machines === '1';
                        document.getElementById('f_party_id').closest('.field').hidden = !machines;
                        document.getElementById('f_employee_id').closest('.field').hidden = machines;
                    };
                    item.addEventListener('change', who);
                    who();
                    item.addEventListener('change', sync);
                    qty.addEventListener('input', sync);
                    sync();
                })();
            </script>
        @endpush
    @endif
</x-layouts.app>
