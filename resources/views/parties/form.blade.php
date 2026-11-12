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
            @php
                // Saved "Other" types are listed by name; "Other…" lets a new one be typed and kept for next time.
                $kindNow = old('kind', $party->kind === \App\Enums\PartyKind::Other && $party->kind_other ? 'custom:'.$party->kind_other : $party->kind?->value);
                $typedNow = old('kind_other', '');
            @endphp
            <div @class(['field', 'has-err' => $errors->has('kind') || $errors->has('kind_other')])>
                <label for="f_kind">{{ __('Type') }} <span class="muted">*</span></label>
                <select id="f_kind" name="kind" required>
                    @foreach (\App\Enums\PartyKind::cases() as $k)
                        @continue($k === \App\Enums\PartyKind::Other)
                        <option value="{{ $k->value }}" @selected($kindNow === $k->value)>{{ $k->label() }}</option>
                    @endforeach
                    @foreach (\App\Models\Party::customTypes() as $t)
                        <option value="custom:{{ $t }}" @selected($kindNow === 'custom:'.$t)>{{ $t }}</option>
                    @endforeach
                    <option value="other" @selected($kindNow === 'other')>{{ __('Other… (type a new one)') }}</option>
                </select>
                <input type="text" id="f_kind_other" name="kind_other" value="{{ $typedNow }}" maxlength="80" placeholder="{{ __('Type the name') }}" aria-label="{{ __('New type') }}" @if ($kindNow !== 'other') hidden @else required @endif style="margin-top:6px">
                @error('kind')<span class="err">{{ $message }}</span>@enderror
                @error('kind_other')<span class="err">{{ $message }}</span>@enderror
            </div>
            @push('scripts')
                <script>
                    (() => {
                        const kind = document.getElementById('f_kind'), typed = document.getElementById('f_kind_other');
                        kind.addEventListener('change', () => {
                            typed.hidden = kind.value !== 'other';
                            typed.required = !typed.hidden;
                            if (!typed.hidden) { typed.focus(); }
                        });
                    })();
                </script>
            @endpush
            <x-field name="phone" :label="__('Phone')" :value="$party->phone" />
            <x-field name="address" :label="__('Address')" :value="$party->address" />
            <x-field name="notes" type="textarea" :label="__('Notes')" :value="$party->notes" full />
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $party->is_active))> {{ __('Active') }}</label></div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $returnTo }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
