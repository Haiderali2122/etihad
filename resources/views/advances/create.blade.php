<x-layouts.app :title="__('Give advance')">
    <x-page-head :title="__('Give advance')" :subtitle="__('The amount comes off this person\'s next salary.')" />
    <form method="POST" action="{{ route('salary-advances.store') }}" class="card form-card">
        @csrf
        <div class="card-b"><div class="form">
            <x-field name="employee_id" :label="__('Employee')" :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->name.' ('.$e->code.')'])" :value="$employeeId" :placeholder="__('Choose employee')" required />
            <x-field name="given_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
            <x-field name="amount" type="number" min="1" :label="__('Amount (Rs)')" required />
            <x-field name="note" :label="__('Reason / note')" />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('salary-advances.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save advance') }}</button></div>
    </form>
</x-layouts.app>
