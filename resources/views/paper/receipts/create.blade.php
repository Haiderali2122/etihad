<x-layouts.app :title="__('Receive truck')">
    <x-page-head :title="__('Receive truck')" :subtitle="__('Enter the paper receipt slip as the truck is unloaded. The rims are added to the party\'s balance straight away.')">
        @can('godowns.manage')
            <x-slot:actions><a class="btn" href="{{ route('godowns.index') }}"><x-icon name="box" />{{ __('Manage godowns') }}</a></x-slot:actions>
        @endcan
    </x-page-head>

    <form method="POST" action="{{ route('paper-receipts.store') }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        <div class="card-b"><div class="form">
            <x-field name="slip_no" :label="__('Slip no.')" :value="$nextSlip" required />
            <x-field name="received_at" type="datetime-local" :label="__('Date and time')" :value="now()->format('Y-m-d\TH:i')" required />
            <x-field name="party_id" :label="__('Party')" :options="$parties->pluck('name', 'id')" :placeholder="__('Choose party')" required />
            <x-field name="paper_type_id" :label="__('Paper type and size')" :options="$paperTypes->mapWithKeys(fn ($t) => [$t->id => $t->code.' — '.$t->label()])" :placeholder="__('Choose paper')" required />
            <x-field name="quantity" type="number" min="1" :label="__('Rims / packets')" required :hint="__('A full load is usually 776 rims.')" />
            <x-field name="truck_no" :label="__('Truck no.')" placeholder="LRL-1819" />
            <x-field name="godown_id" :label="__('Unloaded in')" :options="$godowns->mapWithKeys(fn ($g) => [$g->id => $g->localName()])" :value="$godowns->count() === 1 ? $godowns->first()->id : null" :placeholder="__('Choose location')" required />
            <x-field name="received_by" :label="__('Received by')" :value="$receivedBy" required />
            <x-field name="note" :label="__('Note')" full />
            <x-field name="attachment" type="file" :label="__('Photo of slip or bill')" full />
        </div></div>
        <div class="form-actions">
            <a class="btn" href="{{ route('paper.index') }}">{{ __('Cancel') }}</a>
            <button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save slip') }}</button>
        </div>
    </form>
</x-layouts.app>
