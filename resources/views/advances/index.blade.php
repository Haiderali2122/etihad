<x-layouts.app :title="__('Advances & loans')">
    <x-page-head :title="__('Advances & loans')" :subtitle="__('Cash paid ahead of payday. Open advances come off the next salary automatically.')">
        <x-slot:actions>@include('advances.actions')</x-slot:actions>
    </x-page-head>

    <div class="grid g3">
        <x-kpi icon="wallet" tone="warn" :label="__('Open advances')" :foot="__('To be deducted from the next payroll')">Rs {{ number_format($openTotal) }}</x-kpi>
        <x-kpi icon="cal" :label="__('Given this month')">Rs {{ number_format($monthTotal) }}</x-kpi>
        <x-kpi icon="users" :label="__('Staff with advances')">{{ $advances->getCollection()->pluck('employee_id')->unique()->count() }}</x-kpi>
    </div>

    <div class="card">
        <div class="toolbar">
            @include('advances.tabs')
            <form class="filters" method="GET">
                <select class="select" name="show" aria-label="{{ __('Show') }}" onchange="this.form.submit()">
                    @foreach (['open' => __('Not yet deducted'), 'deducted' => __('Deducted'), 'all' => __('All')] as $k => $l)<option value="{{ $k }}" @selected($show === $k)>{{ $l }}</option>@endforeach
                </select>
                <select class="select" name="employee" aria-label="{{ __('Employee') }}" onchange="this.form.submit()">
                    <option value="">{{ __('All staff') }}</option>
                    @foreach ($employees as $e)<option value="{{ $e->id }}" @selected($employeeId === $e->id)>{{ $e->name }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($advances as $a)
                <tr>
                    <td><a class="main" href="{{ route('employees.show', $a->employee) }}" style="color:inherit;text-decoration:none">{{ $a->employee->localName() }}</a><div class="meta">{{ $a->note }}</div></td>
                    <td>{{ $a->given_on->format('j M Y') }}</td>
                    <td>{{ $a->kind->label() }}@if ($a->foremanSheet)<div class="meta"><a href="{{ route('foreman-sheets.show', $a->foremanSheet) }}">{{ __('via :name', ['name' => $a->foremanSheet->foreman->name]) }}</a></div>@endif</td>
                    <td class="r num"><b>Rs {{ number_format($a->amount) }}</b></td>
                    <td>
                        @if ($a->isDeducted())<x-badge tone="mute">{{ __('Deducted in :month', ['month' => $a->payrollRun->label()]) }}</x-badge>
                        @else<x-badge :tone="$a->status->tone()">{{ $a->status->label() }}</x-badge>@endif
                    </td>
                    <td class="r"><a class="btn sm" href="{{ route('salary-advances.show', $a) }}"><x-icon name="printer" />{{ __('Receipt') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><b>{{ __('No advances here') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $advances->links() }}
    </div>
</x-layouts.app>
