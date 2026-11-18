<x-layouts.app :title="__('Edit cash to foreman')">
    <x-page-head :back="route('foreman-cash.index', ['month' => $cash->month->format('Y-m')])" :title="__('Edit cash to foreman')" />
    <form method="POST" action="{{ route('foreman-cash.update', $cash) }}" class="card form-card">
        @csrf @method('PUT')
        <div class="card-b"><div class="form">
            <x-field name="foreman_id" :label="__('Foreman')" :options="$foremen->pluck('name', 'id')" :value="$cash->foreman_id" required />
            <x-field name="given_on" type="date" :label="__('Date')" :value="$cash->given_on->toDateString()" required />
            <x-field name="amount" type="number" min="1" step="1" :label="__('Amount (Rs)')" :value="$cash->amount" required />
            <x-field name="note" :label="__('Note')" :value="$cash->note" />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('foreman-cash.index', ['month' => $cash->month->format('Y-m')]) }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
