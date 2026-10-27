<x-layouts.app :title="$designation->exists ? __('Edit designation') : __('Add designation')">
    <x-page-head :title="$designation->exists ? __('Edit designation') : __('Add designation')" :subtitle="$designation->exists ? __('Renaming also renames it for every employee who holds it.') : null" />
    <form method="POST" action="{{ $designation->exists ? route('designations.update', $designation) : route('designations.store') }}" class="card form-card">
        @csrf
        @if ($designation->exists) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="name" :label="__('Name')" :value="$designation->name" placeholder="Printer" required />
            <x-field name="name_ur" :label="__('Name in Urdu')" :value="$designation->name_ur" placeholder="پرنٹر" dir="rtl" />
            <x-field name="notes" type="textarea" :label="__('Notes')" :value="$designation->notes" full />
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $designation->is_active))> {{ __('Active — offered when adding or editing an employee') }}</label></div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('designations.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
