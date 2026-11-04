<x-layouts.app :title="__('Edit payment')">
    <x-page-head :back="route('contractors.show', $payment->contractor_id)" :title="__('Edit payment')" :subtitle="$payment->contractor->name" />
    <form method="POST" action="{{ route('contractor-payments.update', $payment) }}" class="card form-card">
        @csrf @method('PUT')
        <div class="card-b"><div class="form">
            <x-field name="kind" :label="__('Type')" :options="collect(\App\Enums\ContractorPaymentKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])" :value="$payment->kind" required />
            <x-field name="amount" type="number" min="1" :label="__('Amount (Rs)')" :value="$payment->amount" required />
            <x-field name="paid_on" type="date" :label="__('Date')" :value="$payment->paid_on->toDateString()" required />
            <x-field name="work_order_id" :label="__('Against order (optional)')" :options="$orders->pluck('number', 'id')" :value="$payment->work_order_id" :placeholder="__('Not for an order')" />
            <x-field name="note" :label="__('Note')" :value="$payment->note" full />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('contractors.show', $payment->contractor_id) }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save changes') }}</button></div>
    </form>
</x-layouts.app>
