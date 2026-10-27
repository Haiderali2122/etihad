<x-layouts.app :title="__('Give loan')">
    <x-page-head :title="__('Give loan')" :subtitle="__('Repaid from salary in monthly instalments. Any month\'s instalment can be changed on that month\'s payroll.')" />
    <form method="POST" action="{{ route('loans.store') }}" class="card form-card">
        @csrf
        <div class="card-b"><div class="form">
            <x-field name="employee_id" :label="__('Employee')" :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->name.' ('.$e->code.')'])" :value="$employeeId" :placeholder="__('Choose employee')" required />
            <x-field name="issued_on" type="date" :label="__('Date given')" :value="today()->toDateString()" required />
            <x-field name="amount" type="number" min="1" :label="__('Loan amount (Rs)')" required />
            <x-field name="monthly_instalment" type="number" min="1" :label="__('Monthly instalment (Rs)')" required />
            <x-field name="first_instalment_month" type="month" :label="__('First instalment in')" :value="now()->addMonthNoOverflow()->format('Y-m')" required />
            <div class="field"><label>{{ __('Repaid in') }}</label><div class="calc" style="padding:10px 14px"><div class="big num" id="months" style="font-size:22px">—</div></div></div>
            <x-field name="note" :label="__('Reason / note')" full />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('loans.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save loan') }}</button></div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const a = document.getElementById('f_amount'), i = document.getElementById('f_monthly_instalment'), out = document.getElementById('months');
                const calc = () => { const n = (+a.value && +i.value) ? Math.ceil(a.value / i.value) : 0; out.textContent = n ? `${n} {{ __('months') }}` : '—'; };
                [a, i].forEach(el => el.addEventListener('input', calc)); calc();
            })();
        </script>
    @endpush
</x-layouts.app>
