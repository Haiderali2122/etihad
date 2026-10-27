<x-layouts.app :title="__('Add expense')">
    <x-page-head :title="__('Add expense')" :subtitle="__('Log each spend with who was paid and why. Spends within the daily limit are approved straight away; anything over goes to the owner.')" />
    <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        <div class="card-b"><div class="form">
            <x-field name="spent_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
            <x-field name="expense_head_id" :label="__('Head')" :options="$heads->mapWithKeys(fn ($h) => [$h->id => $h->localName()])" :placeholder="__('Choose head')" required />
            <x-field name="paid_to" :label="__('Paid to')" required />
            <x-field name="amount" type="number" min="1" :label="__('Amount (Rs)')" required />
            <x-field name="reason" :label="__('Reason')" full required />
            <x-field name="machine_id" :label="__('For machine (optional)')" :options="$machines->mapWithKeys(fn ($m) => [$m->id => $m->code.' '.$m->name])" :placeholder="__('Not for a machine')" />
            <x-field name="attachment" type="file" :label="__('Receipt photo')" />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('expenses.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save expense') }}</button></div>
    </form>
</x-layouts.app>
