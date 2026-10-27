<x-layouts.app :title="$paperType->exists ? __('Edit paper type') : __('Add paper type')">
    <x-page-head :title="$paperType->exists ? __('Edit paper type') : __('Add paper type')" />
    <form method="POST" action="{{ $paperType->exists ? route('paper-types.update', $paperType) : route('paper-types.store') }}" class="card form-card">
        @csrf
        @if ($paperType->exists) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="code" :label="__('Code')" :value="$paperType->code" placeholder="AA-1975" required />
            <x-field name="name" :label="__('Name')" :value="$paperType->name" placeholder="AA printing" required />
            <x-field name="name_ur" :label="__('Name in Urdu')" :value="$paperType->name_ur" dir="rtl" />
            <x-field name="size" :label="__('Size (inches)')" :value="$paperType->size" placeholder="19.75 × 29.75" required />
            <x-field name="gsm" type="number" label="GSM" :value="$paperType->gsm" />
            <x-field name="unit" :label="__('Counted in')" :options="['rims' => __('rims'), 'packets' => __('packets')]" :value="$paperType->unit" required />
            <x-field name="sheets_per_unit" type="number" :label="__('Sheets per rim / packet')" :value="$paperType->sheets_per_unit" required :hint="__('Rim = 500 sheets, bleach card packet = 100')" />
            <x-field name="reorder_level" type="number" :label="__('Reorder level')" :value="$paperType->reorder_level ?? 0" required />
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $paperType->is_active))> {{ __('Active') }}</label></div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('paper-types.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
