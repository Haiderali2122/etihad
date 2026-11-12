{{-- Salaries are paid twice a month: the advance salary on the 22nd and the rest on the 10th; anything extra is a loan. --}}
@can('advances.create')
    <a class="btn primary" href="{{ route('salary-advances.mid-month') }}"><x-icon name="cal" />{{ __('Advance salary (22nd)') }}</a>
    <a class="btn" href="{{ route('payroll.tenth') }}"><x-icon name="wallet" />{{ __('Salaries (10th)') }}</a>
    <a class="btn" href="{{ route('loans.create') }}"><x-icon name="plus" />{{ __('Give loan') }}</a>
@endcan
