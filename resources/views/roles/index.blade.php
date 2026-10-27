<x-layouts.app :title="__('Users & roles')">
    <x-page-head :title="__('Users & roles')" :subtitle="__('Who can sign in, and what each role is allowed to do.')">
        <x-slot:actions><a class="btn primary" href="{{ route('roles.create') }}"><x-icon name="plus" />{{ __('Add role') }}</a></x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="card">
        <div class="toolbar">@include('users.tabs')</div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Role') }}</th><th>{{ __('Permissions') }}</th><th class="r">{{ __('Users') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($roles as $role)
                <tr>
                    <td><div class="main">{{ $role->name }}</div><div class="meta">{{ $role->description }}</div></td>
                    <td>
                        @if ($role->is_super)
                            <x-badge tone="info">{{ __('Full access') }}</x-badge>
                        @else
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="meter" style="width:120px"><i style="width:{{ max(2, count($role->permissions ?? []) / max(1, $permissionCount) * 100) }}%"></i></div>
                                <span class="meta">{{ __(':n of :total', ['n' => count($role->permissions ?? []), 'total' => $permissionCount]) }}</span>
                            </div>
                        @endif
                    </td>
                    <td class="r num"><a href="{{ route('users.index', ['role' => $role->id]) }}">{{ $role->users_count }}</a></td>
                    <td class="r" style="white-space:nowrap">
                        <a class="btn sm" href="{{ route('roles.edit', $role) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                        @unless ($role->is_super)
                            <form method="POST" action="{{ route('roles.destroy', $role) }}" style="display:inline" onsubmit="return confirm(@js(__('Delete the :name role?', ['name' => $role->name])))">
                                @csrf @method('DELETE')
                                <button class="btn sm" type="submit" style="color:var(--bad)" aria-label="{{ __('Delete :name', ['name' => $role->name]) }}"><x-icon name="x" /></button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
</x-layouts.app>
