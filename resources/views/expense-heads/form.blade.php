@php $current = old('color', $head->color); @endphp
<x-layouts.app :title="$head->exists ? __('Edit expense head') : __('Add expense head')">
    <x-page-head :title="$head->exists ? __('Edit expense head') : __('Add expense head')" />
    <form method="POST" action="{{ $head->exists ? route('expense-heads.update', $head) : route('expense-heads.store') }}" class="card form-card">
        @csrf
        @if ($head->exists) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="name" :label="__('Name')" :value="$head->name" placeholder="Petrol" required />
            <x-field name="name_ur" :label="__('Name in Urdu')" :value="$head->name_ur" placeholder="پیٹرول" dir="rtl" />
            <div @class(['field', 'full', 'has-err' => $errors->has('color')])>
                <label>{{ __('Colour') }} <span class="hint">{{ __('Used in the dashboard chart and the roznamcha') }}</span></label>
                <div class="swatches" role="radiogroup" aria-label="{{ __('Colour') }}">
                    @foreach ($palette as $colour)
                        <label class="swatch" style="--c:{{ $colour }}">
                            <input type="radio" name="color" value="{{ $colour }}" @checked(strtoupper($current) === $colour) aria-label="{{ $colour }}"><span></span>
                        </label>
                    @endforeach
                    <label class="swatch custom" title="{{ __('Custom colour') }}">
                        <input type="radio" name="color" value="{{ in_array(strtoupper($current), $palette, true) ? '#000000' : $current }}" id="customRadio" @checked(! in_array(strtoupper($current), $palette, true)) aria-label="{{ __('Custom colour') }}">
                        <input type="color" id="customColour" value="{{ in_array(strtoupper($current), $palette, true) ? '#000000' : $current }}" aria-label="{{ __('Custom colour') }}">
                    </label>
                </div>
                @error('color')<span class="err">{{ $message }}</span>@enderror
            </div>
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $head->is_active))> {{ __('Active — shown when adding an expense') }}</label></div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('expense-heads.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
    @push('scripts')
        <script>
            document.getElementById('customColour').addEventListener('input', e => {
                const radio = document.getElementById('customRadio');
                radio.value = e.target.value;
                radio.checked = true;
            });
        </script>
    @endpush
</x-layouts.app>
