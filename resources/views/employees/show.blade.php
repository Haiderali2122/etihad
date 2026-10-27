<x-layouts.app :title="$employee->name">
    <x-page-head :title="$employee->localName()" :subtitle="collect([$employee->designation, __('No. :code', ['code' => $employee->code]), $employee->foreman ? __('under :name', ['name' => $employee->foreman->name]) : null])->filter()->join(' · ')">
        <x-slot:actions>
            <a class="btn" href="{{ route('employees.index') }}">{{ __('Back') }}</a>
            @can('advances.create')
                @if ($employee->is_active)
                    <a class="btn" href="{{ route('salary-advances.create', ['employee' => $employee->id]) }}"><x-icon name="wallet" />{{ __('Give advance') }}</a>
                    <a class="btn" href="{{ route('loans.create', ['employee' => $employee->id]) }}"><x-icon name="users" />{{ __('Give loan') }}</a>
                @endif
            @endcan
            @can('staff.manage')
                <a class="btn primary" href="{{ route('employees.edit', $employee) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="grid g4">
        <x-kpi icon="wallet" :label="__('Monthly salary')" :foot="__('Rs :n a day this month', ['n' => number_format($employee->monthly_salary / $month->daysInMonth)])">Rs {{ number_format($employee->monthly_salary) }}</x-kpi>
        <x-kpi icon="check" tone="good" :label="__('Present this month')" :foot="__(':a absent, :l leave', ['a' => $attendance->where('status.value', 'absent')->count(), 'l' => $attendance->where('status.value', 'leave')->count()])">{{ $attendance->where('status.value', 'present')->count() }}</x-kpi>
        <x-kpi icon="send" tone="warn" :label="__('Open advances')" :foot="__('Comes off next salary')">Rs {{ number_format($openAdvances) }}</x-kpi>
        <x-kpi icon="users" tone="bad" :label="__('Loan balance')">Rs {{ number_format($loanBalance) }}</x-kpi>
    </div>

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Attendance, :month', ['month' => $month->translatedFormat('F Y')]) }}</h3><div class="sub">{{ __('Overtime this month: :h hours', ['h' => \App\Models\InventoryItem::qty($attendance->sum('overtime_minutes') / 60)]) }}</div></div>
            <div class="legend"><span><i style="background:#7FA0DE"></i>{{ __('Present') }}</span><span><i style="background:#E79A8C"></i>{{ __('Absent') }}</span><span><i style="background:var(--royal-soft)"></i>{{ __('Leave') }}</span><span><i style="background:var(--line)"></i>{{ __('Off') }}</span></div></div>
        <div class="card-b">
            <div class="att" style="grid-template-columns:repeat({{ $month->daysInMonth }},1fr)">
                @for ($d = 1; $d <= $month->daysInMonth; $d++)
                    @php $a = $attendance->get($d); @endphp
                    <i title="{{ $d }} {{ $month->format('M') }}{{ $a ? ': '.$a->status->label() : '' }}" @class([
                        'p' => $a?->status->value === 'present', 'a' => $a?->status->value === 'absent', 'o' => $a?->status->value === 'off',
                        'ot' => $a && $a->overtime_minutes > 0, 'f' => ! $a && $d > today()->day && $month->isSameMonth(today()),
                    ])></i>
                @endfor
            </div>
        </div>
    </div>

    <div class="grid g-half">
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Advances') }}</h3><div class="sub">{{ __('Latest 15') }}</div></div></div>
            <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @forelse ($advances as $a)
                    <tr>
                        <td><a href="{{ route('salary-advances.show', $a) }}">{{ $a->given_on->format('j M Y') }}</a></td>
                        <td>{{ $a->kind->label() }}</td>
                        <td class="r num"><b>Rs {{ number_format($a->amount) }}</b></td>
                        <td>@if ($a->isDeducted())<x-badge tone="mute">{{ __('Deducted') }}</x-badge>@else<x-badge :tone="$a->status->tone()">{{ $a->status->label() }}</x-badge>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="empty">{{ __('No advances') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div></div>
        </div>
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Loans') }}</h3></div></div>
            <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Issued') }}</th><th class="r">{{ __('Amount') }}</th><th class="r">{{ __('Instalment') }}</th><th class="r">{{ __('Balance') }}</th></tr></thead>
                <tbody>
                @forelse ($loans as $l)
                    <tr>
                        <td><a href="{{ route('loans.show', $l) }}">{{ $l->issued_on->format('j M Y') }}</a></td>
                        <td class="r num">Rs {{ number_format($l->amount) }}</td>
                        <td class="r num">Rs {{ number_format($l->monthly_instalment) }}</td>
                        <td class="r num">@if ($l->isCleared())<x-badge tone="good">{{ __('Cleared') }}</x-badge>@else<b>Rs {{ number_format($l->balance()) }}</b>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="empty">{{ __('No loans') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Salary slips') }}</h3></div></div>
        <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Month') }}</th><th class="r">{{ __('Overtime') }}</th><th class="r">{{ __('Deductions') }}</th><th class="r">{{ __('Net pay') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($payslips as $p)
                <tr>
                    <td>{{ $p->run->label() }}</td>
                    <td class="r num">Rs {{ number_format($p->overtime_amount) }}</td>
                    <td class="r num">Rs {{ number_format($p->absence_deduction + $p->deductions()) }}</td>
                    <td class="r num"><b>Rs {{ number_format($p->net_pay) }}</b></td>
                    <td><x-badge :tone="$p->run->status->tone()">{{ $p->run->status->label() }}</x-badge></td>
                    <td class="r"><a class="btn sm" href="{{ route('payroll.slips', [$p->run, 'line' => $p->id]) }}"><x-icon name="printer" />{{ __('Slip') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty">{{ __('No payroll yet') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>

    @can('staff.manage')
        @if ($canDelete)
            <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="return confirm(@js(__('Delete :name?', ['name' => $employee->name])))">
                @csrf @method('DELETE')
                <button class="btn" type="submit" style="color:var(--bad)"><x-icon name="x" />{{ __('Delete employee') }}</button>
            </form>
        @endif
    @endcan
</x-layouts.app>
