@php $advance ??= null; @endphp
<x-layouts.app :title="$advance ? __('Edit advance') : __('Give advance')">
    <x-page-head :back="$advance ? route('salary-advances.show', $advance) : ($employeeId ? route('employees.show', $employeeId) : route('salary-advances.index'))" :title="$advance ? __('Edit advance') : __('Give advance')" :subtitle="__('The amount comes off this person\'s next salary.')" />
    <form method="POST" action="{{ $advance ? route('salary-advances.update', $advance) : route('salary-advances.store') }}" class="card form-card">
        @csrf
        @if ($advance) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="employee_id" :label="__('Employee')" :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->name.' ('.$e->code.')'])" :value="$employeeId" :placeholder="__('Choose employee')" required />
            <x-field name="given_on" type="date" :label="__('Date')" :value="$advance?->given_on->toDateString() ?? today()->toDateString()" required />
            <x-field name="amount" type="number" min="1" :label="__('Amount (Rs)')" :value="$advance?->amount" required />
            <x-field name="note" :label="__('Reason / note')" :value="$advance?->note" />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ ($employeeId ? route('employees.show', $employeeId) : route('salary-advances.index')) }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ $advance ? __('Save changes') : __('Save advance') }}</button></div>
    </form>
</x-layouts.app>
