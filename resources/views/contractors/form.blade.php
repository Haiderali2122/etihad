<x-layouts.app :title="$contractor->exists ? __('Edit contractor') : __('Add contractor')">
    <x-page-head :title="$contractor->exists ? __('Edit contractor') : __('Add contractor')" :subtitle="__('Usual rates are pre-filled on each job and can be changed there.')" />
    <form method="POST" action="{{ $contractor->exists ? route('contractors.update', $contractor) : route('contractors.store') }}" class="card form-card">
        @csrf
        @if ($contractor->exists) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="name" :label="__('Name')" :value="$contractor->name" placeholder="Rashid Binding House" required />
            <x-field name="name_ur" :label="__('Name in Urdu')" :value="$contractor->name_ur" dir="rtl" />
            <x-field name="work" :label="__('Does')" :options="collect(\App\Enums\ContractorWork::cases())->mapWithKeys(fn ($w) => [$w->value => $w->label()])" :value="$contractor->work" required />
            <x-field name="phone" :label="__('Phone')" :value="$contractor->phone" />
            <x-field name="folding_rate" type="number" step="0.01" min="0" :label="__('Folding rate (per 1,000 forms)')" :value="$contractor->folding_rate" placeholder="35" />
            <x-field name="form_rate" type="number" step="0.01" min="0" :label="__('Binding rate per form (per copy)')" :value="$contractor->form_rate" placeholder="0.60" />
            <x-field name="copy_rate" type="number" step="0.01" min="0" :label="__('Binding rate per copy')" :value="$contractor->copy_rate" placeholder="2.50" :hint="__('Binding = ((forms × rate per form) + rate per copy) × copies')" />
            <x-field name="notes" type="textarea" :label="__('Notes')" :value="$contractor->notes" full />
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $contractor->is_active))> {{ __('Active') }}</label></div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $contractor->exists ? route('contractors.show', $contractor) : route('contractors.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
