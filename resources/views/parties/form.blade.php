@php
    $returnTo ??= route('parties.index');
    $heading = $party->exists ? __('Edit party') : ($party->kind === \App\Enums\PartyKind::Buyer ? __('Add buyer') : __('Add party'));
@endphp
<x-layouts.app :title="$heading">
    <x-page-head :back="$returnTo" :title="$heading" />
    <form method="POST" action="{{ $party->exists ? route('parties.update', $party) : route('parties.store') }}" class="card form-card">
        @csrf
        @if ($party->exists) @method('PUT') @endif
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
        <div class="card-b"><div class="form">
            <x-field name="name" :label="__('Name')" :value="$party->name" required />
            <x-field name="kind" :label="__('Type')" :options="collect(\App\Enums\PartyKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])" :value="$party->kind" required />
            <x-field name="phone" :label="__('Phone')" :value="$party->phone" />
            <x-field name="address" :label="__('Address')" :value="$party->address" />
            <x-field name="notes" type="textarea" :label="__('Notes')" :value="$party->notes" full />
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $party->is_active))> {{ __('Active') }}</label></div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $returnTo }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
