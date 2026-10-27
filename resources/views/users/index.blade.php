<x-layouts.app :title="__('Users & roles')">
    <x-page-head :title="__('Users & roles')" :subtitle="__('Who can sign in, and what each role is allowed to do.')">
        <x-slot:actions><a class="btn primary" href="{{ route('users.create') }}"><x-icon name="plus" />{{ __('Add user') }}</a></x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="card">
        <div class="toolbar">
            @include('users.tabs')
            <form class="filters" method="GET">
                <select class="select" name="role" aria-label="{{ __('Role') }}" onchange="this.form.submit()">
                    <option value="">{{ __('All roles') }}</option>
                    @foreach ($roles as $role)<option value="{{ $role->id }}" @selected($roleId === $role->id)>{{ $role->name }} ({{ $role->users_count }})</option>@endforeach
                </select>
            </form>
        </div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Role') }}</th><th>{{ __('Language') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($users as $u)
                <tr>
                    <td><div class="who"><span class="avatar sm">{{ $u->initials() }}</span><div><div class="main">{{ $u->name }}</div>@if ($u->is(auth()->user()))<div class="meta">{{ __('You') }}</div>@endif</div></div></td>
                    <td>{{ $u->email }}</td>
                    <td><x-badge :tone="$u->isOwner() ? 'info' : 'mute'">{{ $u->role?->name ?? '—' }}</x-badge></td>
                    <td>{{ $u->locale === 'ur' ? 'اردو' : 'English' }}</td>
                    <td><x-badge :tone="$u->is_active ? 'good' : 'bad'">{{ $u->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                    <td class="r" style="white-space:nowrap">
                        <a class="btn sm" href="{{ route('users.edit', $u) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                        @unless ($u->is(auth()->user()))
                            <form method="POST" action="{{ route('users.destroy', $u) }}" style="display:inline" onsubmit="return confirm(@js(__('Delete :name? They will no longer be able to sign in.', ['name' => $u->name])))">
                                @csrf @method('DELETE')
                                <button class="btn sm" type="submit" style="color:var(--bad)" aria-label="{{ __('Delete :name', ['name' => $u->name]) }}"><x-icon name="x" /></button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><b>{{ __('No users') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $users->links() }}
    </div>
</x-layouts.app>
