@php $granted = old('permissions', $role->permissions ?? []); @endphp
<x-layouts.app :title="$role->exists ? __('Edit role') : __('Add role')">
    <x-page-head :title="$role->exists ? __('Edit role') : __('Add role')" :subtitle="__('Tick what people with this role may do. Screens they cannot use are hidden from their menu.')" />

    <form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}" class="card" style="max-width:980px">
        @csrf
        @if ($role->exists) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="name" :label="__('Role name')" :value="$role->name" placeholder="Store keeper" required />
            <x-field name="description" :label="__('Description')" :value="$role->description" />
        </div></div>

        <div class="card-h" style="padding-top:0"><div><h3>{{ __('Permissions') }}</h3>
            <div class="sub">{{ $role->is_super ? __('This role has full access to everything, including future screens.') : __('Use the group box to tick or clear a whole group.') }}</div></div></div>
        @error('permissions')<div class="card-b" style="padding-bottom:0"><div class="flash err">{{ $message }}</div></div>@enderror

        <div class="card-b"><div class="grid g-half">
            @foreach (config('permissions') as $group => $items)
                <fieldset class="card" style="padding:14px 16px;margin:0" data-group @disabled($role->is_super)>
                    <legend class="sr-only">{{ __($group) }}</legend>
                    <label class="check-row" style="border-bottom:1px solid var(--line-2);padding-bottom:10px;margin-bottom:6px">
                        <input type="checkbox" data-all @checked($role->is_super)> <b>{{ __($group) }}</b>
                    </label>
                    @foreach ($items as $key => $label)
                        <label class="check-row" style="font-weight:500;padding:5px 0">
                            <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked($role->is_super || in_array($key, $granted, true))>
                            <span>{{ __($label) }}</span>
                        </label>
                    @endforeach
                </fieldset>
            @endforeach
        </div></div>

        <div class="form-actions"><a class="btn" href="{{ route('roles.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save role') }}</button></div>
    </form>

    @push('scripts')
        <script>
            document.querySelectorAll('[data-group]').forEach(group => {
                const all = group.querySelector('[data-all]'), boxes = [...group.querySelectorAll('input[name="permissions[]"]')];
                const sync = () => { const n = boxes.filter(b => b.checked).length; all.checked = n === boxes.length; all.indeterminate = n > 0 && n < boxes.length; };
                all.addEventListener('change', () => { boxes.forEach(b => b.checked = all.checked); sync(); });
                boxes.forEach(b => b.addEventListener('change', sync));
                sync();
            });
        </script>
    @endpush
</x-layouts.app>
