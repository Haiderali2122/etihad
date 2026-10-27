<x-layouts.app :title="__('Staff')">
    <x-page-head :title="__('Staff')" :subtitle="__('Everyone on the payroll. The employee no. matches their number on the biometric device.')">
        <x-slot:actions>
            @can('attendance.manage')<a class="btn" href="{{ route('attendance.day') }}"><x-icon name="fingerprint" />{{ __('Mark attendance') }}</a>@endcan
            @can('staff.manage')<a class="btn" href="{{ route('designations.index') }}"><x-icon name="file" />{{ __('Designations') }}</a>@endcan
            @can('staff.manage')<a class="btn primary" href="{{ route('employees.create') }}"><x-icon name="plus" />{{ __('Add employee') }}</a>@endcan
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="grid g3">
        <x-kpi icon="users" :label="__('Active staff')">{{ $activeCount }}</x-kpi>
        <x-kpi icon="check" tone="good" :label="__('Present today')" :foot="__(':n not marked yet', ['n' => max(0, $activeCount - $presentToday)])">{{ $presentToday }}</x-kpi>
        <x-kpi icon="wallet" tone="warn" :label="__('Monthly salaries')" :foot="__('Before overtime and deductions')">Rs {{ number_format($payrollTotal) }}</x-kpi>
    </div>

    <div class="card">
        <div class="toolbar">
            <div class="tabs" role="tablist">
                @foreach (['active' => __('Active'), 'inactive' => __('Left / inactive'), 'all' => __('All')] as $key => $label)
                    <a href="{{ route('employees.index', array_filter(['status' => $key, 'q' => $search])) }}" @class(['tab', 'on' => $status === $key]) style="text-decoration:none">{{ $label }}</a>
                @endforeach
            </div>
            <form class="filters" method="GET">
                <input type="hidden" name="status" value="{{ $status }}">
                <input class="select" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search name, no. or job') }}" aria-label="{{ __('Search') }}" style="min-width:220px">
            </form>
        </div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('No.') }}</th><th>{{ __('Designation') }}</th><th>{{ __('Foreman') }}</th><th class="r">{{ __('Salary') }}</th><th class="r">{{ __('Open advances') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($employees as $e)
                <tr class="row-link" data-href="{{ route('employees.show', $e) }}">
                    <td><a href="{{ route('employees.show', $e) }}" class="who" style="color:inherit;text-decoration:none"><span class="avatar sm">{{ $e->initials() }}</span><span><span class="main">{{ $e->localName() }}</span>@if ($e->is_foreman)<div class="meta">{{ __('Foreman') }}</div>@endif</span></a></td>
                    <td><span class="tag">{{ $e->code }}</span></td>
                    <td>{{ $e->designation }}</td>
                    <td>{{ $e->foreman?->name ?? '—' }}</td>
                    <td class="r num">Rs {{ number_format($e->monthly_salary) }}</td>
                    <td class="r num">{{ $e->open_advances ? 'Rs '.number_format($e->open_advances) : '—' }}</td>
                    <td><x-badge :tone="$e->is_active ? 'good' : 'mute'">{{ $e->is_active ? __('Active') : ($e->left_on ? __('Left :date', ['date' => $e->left_on->format('j M Y')]) : __('Inactive')) }}</x-badge></td>
                    <td class="r" style="white-space:nowrap">
                        <a class="btn sm" href="{{ route('employees.show', $e) }}"><x-icon name="eye" />{{ __('View') }}</a>
                        @can('staff.manage')<a class="btn sm" href="{{ route('employees.edit', $e) }}" aria-label="{{ __('Edit :name', ['name' => $e->name]) }}"><x-icon name="edit" /></a>@endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><b>{{ __('No staff yet') }}</b>{{ __('Add employees with their monthly salary to start attendance and payroll.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</x-layouts.app>
