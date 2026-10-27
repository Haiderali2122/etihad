<x-layouts.app :title="$category->exists ? __('Edit category') : __('Add category')">
    <x-page-head :title="$category->exists ? __('Edit category') : __('Add category')" />
    <form method="POST" action="{{ $category->exists ? route('inventory-categories.update', $category) : route('inventory-categories.store') }}" class="card form-card">
        @csrf
        @if ($category->exists) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="name" :label="__('Name')" :value="$category->name" placeholder="Ink" required />
            <x-field name="name_ur" :label="__('Name in Urdu')" :value="$category->name_ur" placeholder="سیاہی" dir="rtl" />
            <div class="field full">
                <input type="hidden" name="tracks_stock" value="0">
                <label class="check-row"><input type="checkbox" name="tracks_stock" value="1" @checked(old('tracks_stock', $category->tracks_stock))> {{ __('Track stock for this category') }}</label>
                <span class="hint">{{ __('Turn off for paper: its bills are recorded here, but its stock is kept on the Paper stock screen.') }}</span>
            </div>
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))> {{ __('Active') }}</label></div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('inventory-categories.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
