<x-layouts.app :title="$processor->exists ? __('Edit processor') : __('Add processor')">
    <x-page-head :back="$returnTo" :title="$processor->exists ? __('Edit processor') : __('Add processor')" />
    <form method="POST" action="{{ $processor->exists ? route('processors.update', $processor) : route('processors.store') }}" class="card form-card">
        @csrf
        @if ($processor->exists) @method('PUT') @endif
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
        <div class="card-b"><div class="form">
            <x-field name="name" :label="__('Name')" :value="$processor->name" required />
            <x-field name="phone" :label="__('Phone')" :value="$processor->phone" />
            <x-field name="address" :label="__('Address')" :value="$processor->address" />
            <x-field name="notes" type="textarea" :label="__('Notes')" :value="$processor->notes" full />
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $processor->is_active))> {{ __('Active') }}</label></div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $returnTo }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
