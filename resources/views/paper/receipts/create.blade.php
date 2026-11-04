@php
    $receipt ??= null;
    $editing = $receipt !== null;
@endphp
<x-layouts.app :title="$editing ? __('Edit slip :no', ['no' => $receipt->slip_no]) : __('Receive truck')">
    <x-page-head :back="$editing ? route('paper-receipts.show', $receipt) : route('paper.index')" :title="$editing ? __('Edit slip :no', ['no' => $receipt->slip_no]) : __('Receive truck')" :subtitle="$editing ? __('Changes made by the manager go back to the owner for approval.') : __('Enter the paper receipt slip as the truck is unloaded. The rims are added to the party\'s balance straight away.')">
        @can('godowns.manage')
            <x-slot:actions><a class="btn" href="{{ route('godowns.index') }}"><x-icon name="box" />{{ __('Manage godowns') }}</a></x-slot:actions>
        @endcan
    </x-page-head>

    <form method="POST" action="{{ $editing ? route('paper-receipts.update', $receipt) : route('paper-receipts.store') }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="slip_no" :label="__('Slip no.')" :value="$editing ? $receipt->slip_no : $nextSlip" required />
            <x-field name="received_at" type="datetime-local" :label="__('Date and time')" :value="$editing ? $receipt->received_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')" required />
            <x-field name="party_id" :label="__('Party')" :options="$parties->pluck('name', 'id')" :value="$receipt?->party_id" :placeholder="__('Choose party')" required />
            <x-field name="paper_type_id" :label="__('Paper type and size')" :options="$paperTypes->mapWithKeys(fn ($t) => [$t->id => $t->code.' — '.$t->label()])" :value="$receipt?->paper_type_id" :placeholder="__('Choose paper')" required />
            <x-field name="quantity" type="number" min="1" :label="__('Rims / packets')" :value="$receipt?->quantity" required :hint="__('A full load is usually 776 rims.')" />
            <x-field name="truck_no" :label="__('Truck no.')" :value="$receipt?->truck_no" placeholder="LRL-1819" />
            <x-field-other name="godown_id" :label="__('Unloaded in')" :options="$godowns->mapWithKeys(fn ($g) => [$g->id => $g->localName()])" :value="$editing ? $receipt->godown_id : ($godowns->count() === 1 ? $godowns->first()->id : null)" :placeholder="__('Choose location')" required />
            <x-field name="received_by" :label="__('Received by')" :value="$editing ? $receipt->received_by : $receivedBy" required />
            <x-field name="note" :label="__('Note')" :value="$receipt?->note" full />
            <x-field name="attachment" type="file" :label="__('Photo of slip or bill')" :hint="$editing && $receipt->attachment ? __('Leave empty to keep the current photo.') : null" full />
        </div></div>
        <div class="form-actions">
            <a class="btn" href="{{ $editing ? route('paper-receipts.show', $receipt) : route('paper.index') }}">{{ __('Cancel') }}</a>
            <button class="btn primary" type="submit"><x-icon name="check" />{{ $editing ? __('Save changes') : __('Save slip') }}</button>
        </div>
    </form>
</x-layouts.app>
