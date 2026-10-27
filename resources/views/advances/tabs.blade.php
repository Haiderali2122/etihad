<div class="tabs" role="tablist">
    <a href="{{ route('salary-advances.index') }}" @class(['tab', 'on' => request()->routeIs('salary-advances.index')]) style="text-decoration:none">{{ __('Advances') }}</a>
    <a href="{{ route('loans.index') }}" @class(['tab', 'on' => request()->routeIs('loans.index')]) style="text-decoration:none">{{ __('Loans') }}</a>
</div>
