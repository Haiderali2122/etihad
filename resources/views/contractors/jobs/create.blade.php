<x-layouts.app :title="__('Give job')">
    <x-page-head :title="__('Give folding or binding job')" :subtitle="__('The amount is worked out from the forms, copies and rates.')" />
    @if ($contractors->isEmpty())
        <div class="card"><div class="empty"><b>{{ __('No contractors yet') }}</b><a href="{{ route('contractors.create') }}">{{ __('Add a contractor first.') }}</a></div></div>
    @else
        <form method="POST" action="{{ route('contractor-jobs.store') }}" class="card form-card">
            @csrf
            <div class="card-b"><div class="form">
                <div class="field full">
                    <label for="f_work_order_id">{{ __('Work order') }}</label>
                    <select id="f_work_order_id" name="work_order_id">
                        <option value="">{{ __('Not for an order') }}</option>
                        @foreach ($orders as $o)
                            <option value="{{ $o->id }}" data-forms="{{ \App\Models\InventoryItem::qty($o->forms) }}" data-copies="{{ $o->copies }}" data-folding="{{ $o->bookFormat->folding_rate }}" data-binding="{{ $o->bookFormat->binding_rate }}" @selected((string) old('work_order_id', $order?->id) === (string) $o->id)>{{ $o->number }} — {{ $o->bookFormat->book->name }} ({{ number_format($o->copies) }})</option>
                        @endforeach
                    </select>
                </div>
                <x-field name="contractor_id" :label="__('Contractor')" :options="$contractors->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.$c->work->label()])" :value="$contractorId" :placeholder="__('Choose contractor')" required />
                <x-field name="kind" :label="__('Work')" :options="collect(\App\Enums\ContractorJobKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])" :value="$kind" required />
                <x-field name="forms" type="number" step="0.5" min="0.5" :label="__('Forms per copy')" :value="$order ? \App\Models\InventoryItem::qty($order->forms) : null" required />
                <x-field name="copies" type="number" min="1" :label="__('Copies')" :value="$order?->copies" required />
                <x-field name="folding_rate" type="number" step="0.01" min="0" :label="__('Folding rate (per 1,000 forms)')" />
                <x-field name="form_rate" type="number" step="0.01" min="0" :label="__('Binding rate per form')" />
                <x-field name="copy_rate" type="number" step="0.01" min="0" :label="__('Binding rate per copy')" />
                <x-field name="job_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                <div class="field full"><div class="calc"><div class="lbl">{{ __('Amount') }}</div><div class="big num" id="jobAmount">Rs 0</div><div class="eq" id="jobEq" dir="ltr"></div></div></div>
                <x-field name="note" :label="__('Note')" full />
            </div></div>
            <div class="form-actions"><a class="btn" href="{{ url()->previous() }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save job') }}</button></div>
        </form>
        @push('scripts')
            <script>
                (() => {
                    const $ = id => document.getElementById(id);
                    const rates = @json($contractors->mapWithKeys(fn ($c) => [$c->id => ['folding' => $c->folding_rate, 'form' => $c->form_rate, 'copy' => $c->copy_rate]]));
                    const order = $('f_work_order_id'), contractor = $('f_contractor_id'), kind = $('f_kind');
                    const forms = $('f_forms'), copies = $('f_copies'), fold = $('f_folding_rate'), perForm = $('f_form_rate'), perCopy = $('f_copy_rate');
                    const r10 = n => Math.round(n / 10) * 10, fmt = n => n.toLocaleString('en-PK');
                    const toggle = () => {
                        const binding = kind.value === 'binding';
                        fold.closest('.field').hidden = binding; perForm.closest('.field').hidden = !binding; perCopy.closest('.field').hidden = !binding;
                    };
                    const fill = () => {
                        const o = order.selectedOptions[0]?.dataset || {}, c = rates[contractor.value] || {};
                        if (o.forms) { forms.value = o.forms; copies.value = o.copies; }
                        fold.value = c.folding ?? o.folding ?? fold.value;
                        perForm.value = c.form ?? perForm.value;
                        perCopy.value = c.copy ?? o.binding ?? perCopy.value;
                        calc();
                    };
                    const calc = () => {
                        const f = +forms.value || 0, n = +copies.value || 0;
                        const amount = kind.value === 'binding' ? r10((f * (+perForm.value || 0) + (+perCopy.value || 0)) * n) : r10(f * n / 1000 * (+fold.value || 0));
                        $('jobAmount').textContent = 'Rs ' + fmt(amount);
                        $('jobEq').textContent = kind.value === 'binding'
                            ? `((${f} × ${perForm.value || 0}) + ${perCopy.value || 0}) × ${fmt(n)}`
                            : `(${f} × ${fmt(n)} ÷ 1000) × ${fold.value || 0}`;
                    };
                    order.addEventListener('change', fill); contractor.addEventListener('change', fill);
                    kind.addEventListener('change', () => { toggle(); calc(); });
                    [forms, copies, fold, perForm, perCopy].forEach(el => el.addEventListener('input', calc));
                    toggle(); fill();
                })();
            </script>
        @endpush
    @endif
</x-layouts.app>
