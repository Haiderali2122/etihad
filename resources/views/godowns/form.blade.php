<x-layouts.app :title="$godown->exists ? __('Edit godown') : __('Add godown')">
    <x-page-head :title="$godown->exists ? __('Edit godown') : __('Add godown')" />
    <form method="POST" action="{{ $godown->exists ? route('godowns.update', $godown) : route('godowns.store') }}" class="card form-card">
        @csrf
        @if ($godown->exists) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="name" :label="__('Name')" :value="$godown->name" placeholder="Godown C" required />
            <x-field name="name_ur" :label="__('Name in Urdu')" :value="$godown->name_ur" placeholder="گودام سی" dir="rtl" />
            <x-field name="address" :label="__('Address')" :value="$godown->address" full />
            <x-field name="notes" type="textarea" :label="__('Notes')" :value="$godown->notes" full />
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $godown->is_active))> {{ __('Active — shown when receiving a truck') }}</label></div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('godowns.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
