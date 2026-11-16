<x-layouts.app :title="__('Edit khata line')">
    <x-page-head :back="route('rim-loadings.index')" :title="__('Edit khata line')" :subtitle="$line->workOrder->number.' · '.$line->workOrder->bookName()" />
    <form method="POST" action="{{ route('rim-loadings.update', $line) }}" class="card form-card">
        @csrf @method('PUT')
        <div class="card-b"><div class="form">
            <x-field name="kind" :label="__('Type')" :options="collect(\App\Enums\RimMove::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])" :value="$line->kind" required />
            <x-field name="loaded_on" type="date" :label="__('Date')" :value="$line->loaded_on->toDateString()" required />
            <x-field name="rims" type="number" step="0.5" min="0.5" :label="__('Rims')" :value="\App\Models\InventoryItem::qty($line->rims)" required />
            <x-field name="rate_per_rim" type="number" step="0.01" min="0" :label="__('Rate per rim (Rs)')" :value="\App\Models\InventoryItem::qty($line->rate_per_rim)" required />
            <div class="field"><label>{{ __('Amount') }}</label><div class="calc" style="padding:10px 14px"><div class="big num" id="amountOut" style="font-size:22px">Rs 0</div></div></div>
            <x-field name="note" :label="__('Note')" :value="$line->note" />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('rim-loadings.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const r = document.getElementById('f_rims'), p = document.getElementById('f_rate_per_rim'), out = document.getElementById('amountOut');
                const calc = () => { out.textContent = 'Rs ' + Math.round((+r.value || 0) * (+p.value || 0)).toLocaleString('en-PK'); };
                [r, p].forEach(el => el.addEventListener('input', calc));
                calc();
            })();
        </script>
    @endpush
</x-layouts.app>
