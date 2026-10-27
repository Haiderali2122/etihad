<div class="tabs" role="tablist">
    <a href="{{ route('users.index') }}" @class(['tab', 'on' => request()->routeIs('users.*')]) style="text-decoration:none">{{ __('Users') }}</a>
    <a href="{{ route('roles.index') }}" @class(['tab', 'on' => request()->routeIs('roles.*')]) style="text-decoration:none">{{ __('Roles & permissions') }}</a>
</div>
