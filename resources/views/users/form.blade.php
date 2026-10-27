<x-layouts.app :title="$user->exists ? __('Edit user') : __('Add user')">
    <x-page-head :title="$user->exists ? __('Edit user') : __('Add user')" :subtitle="$user->exists ? $user->email : __('The person signs in with this email and password.')" />

    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card form-card" autocomplete="off">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="name" :label="__('Name')" :value="$user->name" required />
            <x-field name="email" type="email" :label="__('Email')" :value="$user->email" required />
            <x-field name="role_id" :label="__('Role')" :options="$roles->mapWithKeys(fn ($r) => [$r->id => $r->name.($r->is_super ? ' — '.__('full access') : '')])" :value="$user->role_id" :placeholder="__('Choose role')" required
                     :hint="__('Edit what each role can do under Roles & permissions.')" />
            <x-field name="locale" :label="__('Language')" :options="['en' => 'English', 'ur' => 'اردو']" :value="$user->locale" required />
            <x-field name="password" type="password" :label="$user->exists ? __('New password') : __('Password')" :required="! $user->exists" autocomplete="new-password"
                     :hint="$user->exists ? __('Leave blank to keep the current password.') : __('At least 8 characters.')" />
            <x-field name="password_confirmation" type="password" :label="__('Confirm password')" :required="! $user->exists" autocomplete="new-password" />
            <div @class(['field', 'full', 'has-err' => $errors->has('is_active')])>
                <input type="hidden" name="is_active" value="0">
                <label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> {{ __('Active — can sign in') }}</label>
                @error('is_active')<span class="err">{{ $message }}</span>@enderror
            </div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('users.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save user') }}</button></div>
    </form>
</x-layouts.app>
