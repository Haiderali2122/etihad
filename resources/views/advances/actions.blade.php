@can('advances.create')
    <a class="btn" href="{{ route('foreman-sheets.create') }}"><x-icon name="users" />{{ __('Foreman sheet') }}</a>
    <a class="btn" href="{{ route('salary-advances.mid-month') }}"><x-icon name="cal" />{{ __('Mid-month advance') }}</a>
    <a class="btn" href="{{ route('loans.create') }}"><x-icon name="plus" />{{ __('Give loan') }}</a>
    <a class="btn primary" href="{{ route('salary-advances.create') }}"><x-icon name="wallet" />{{ __('Give advance') }}</a>
@endcan
