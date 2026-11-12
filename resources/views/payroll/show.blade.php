@php
    $rules = $run->rules ?? [];
    $editable = $run->isDraft() && auth()->user()->can('payroll.prepare');
@endphp
<x-layouts.app :title="__('Payroll :month', ['month' => $run->label()])">
    <x-page-head :title="__('Salaries (10th) — :month', ['month' => $run->label()])" :subtitle="__('The rest of :month\'s salary and overtime, less the advance salary of the 22nd and any loan instalment, paid on :date. Type what each person already took from the foreman; Give now is what to hand over.', ['month' => $run->label(), 'date' => $run->pay_date?->format('j M Y') ?? __('the 10th')])">
        <x-slot:actions>
            <x-badge :tone="$run->status->tone()">{{ $run->status->label() }}</x-badge>
            <a class="btn" href="{{ route('payroll.index') }}">{{ __('Back') }}</a>
            <a class="btn" href="{{ route('payroll.slips', $run) }}"><x-icon name="printer" />{{ __('All slips') }}</a>
            @if ($run->isDraft())@can('payroll.prepare')<x-delete-button :action="route('records.destroy', ['payroll-run', $run->id])" :confirm="__('Delete the :month payroll draft?', ['month' => $run->label()])" :label="__('Delete')" :redirect="route('payroll.index')" />@endcan @endif
            @if ($run->isDraft())
                @can('payroll.prepare')
                    <form method="POST" action="{{ route('payroll.recalculate', $run) }}">@csrf<button class="btn" type="submit"><x-icon name="swap" />{{ __('Recalculate') }}</button></form>
                @endcan
                @can('payroll.finalize')
                    <form method="POST" action="{{ route('payroll.finalize', $run) }}" onsubmit="return confirm(@js(__('Finalize :month? Advances and loan instalments will be marked as deducted.', ['month' => $run->label()])))">@csrf<button class="btn good" type="submit"><x-icon name="check" />{{ __('Finalize') }}</button></form>
                @endcan
            @else
                @can('payroll.finalize')
                    <form method="POST" action="{{ route('payroll.reopen', $run) }}" onsubmit="return confirm(@js(__('Reopen this payroll? Its advances and loan instalments will be released.')))">@csrf<button class="btn" type="submit"><x-icon name="back" />{{ __('Reopen') }}</button></form>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif
    @if ($run->isDraft() && $unmarked)
        <div class="alert"><x-icon name="alert" /><div>
            <b>{{ trans_choice(':count day has no attendance.|:count days have no attendance.', $unmarked) }}</b>
            {{ ($rules['unmarked_as'] ?? 'present') === 'absent' ? __('They are counted as absent and deducted.') : __('They are counted as present (no deduction).') }}
            @can('attendance.manage')<a href="{{ route('attendance.index', ['month' => $run->month->format('Y-m')]) }}" style="color:#4D3000;font-weight:700">{{ __('Review attendance') }}</a>@endcan
        </div></div>
    @endif
    @if ($run->finalized_at)
        <div class="flash"><x-icon name="check" />{{ __('Finalized by :name on :date.', ['name' => $run->finalizer?->name ?? '—', 'date' => $run->finalized_at->format('j M Y H:i')]) }}</div>
    @endif

    <div class="grid g4">
        <x-kpi icon="wallet" :label="__('Salaries')">Rs {{ number_format($totals['salary']) }}</x-kpi>
        <x-kpi icon="clock" tone="good" :label="__('Overtime')">Rs {{ number_format($totals['overtime']) }}</x-kpi>
        <x-kpi icon="down" tone="bad" :label="__('Advance salaries and loans')">Rs {{ number_format($totals['advances'] + $totals['loans']) }}</x-kpi>
        <x-kpi icon="check" tone="warn" :label="__('Net to pay')" :foot="__('Rounded to the nearest Rs 10')">Rs {{ number_format($totals['net']) }}</x-kpi>
    </div>

    <form method="POST" action="{{ route('payroll.lines.update', $run) }}" class="card">
        @csrf @method('PUT')
        <div class="toolbar">
            <span class="muted">{{ __('Overtime: daily rate ÷ :d × :m per hour · normal day :h hours', ['d' => $rules['ot_divisor'] ?? 6, 'm' => $rules['ot_multiplier'] ?? 1, 'h' => $rules['standard_hours'] ?? 8]) }}</span>
            @if ($editable)<button class="btn primary sm" type="submit"><x-icon name="check" />{{ __('Save changes') }}</button>@endif
        </div>
        @php $foremanLines = $lines->filter(fn ($l) => $l->employee?->is_foreman); @endphp
        @if ($foremanLines->isNotEmpty())
            <div class="card-b" style="display:flex;flex-wrap:wrap;gap:16px">
                @foreach ($foremanLines as $fl)
                    <div class="field" style="min-width:220px">
                        <label for="cash_{{ $fl->employee_id }}">{{ __('Cash given to :name (23rd–9th)', ['name' => $fl->employee->name]) }}</label>
                        @if ($editable)<input class="select" type="number" min="0" step="10" id="cash_{{ $fl->employee_id }}" name="cash[{{ $fl->employee_id }}]" value="{{ old("cash.{$fl->employee_id}", $cash->get($fl->employee_id)?->amount) }}" data-cash="{{ $fl->employee_id }}" style="text-align:end">@else<b class="num">Rs {{ number_format($cash->get($fl->employee_id)?->amount ?? 0) }}</b>@endif
                        @error("cash.{$fl->employee_id}")<span class="err">{{ $message }}</span>@enderror
                    </div>
                @endforeach
            </div>
        @endif
        <div class="tbl-wrap"><table class="pay-grid">
            <thead><tr>
                <th>{{ __('Employee') }}</th><th class="r">{{ __('Salary') }}</th><th class="r">{{ __('Per day') }}</th><th class="r">{{ __('Days paid') }}</th>
                <th class="r">{{ __('Earned') }}</th><th class="r">{{ __('Overtime') }}</th><th class="r">{{ __('Bonus') }}</th>
                <th class="r">{{ __('Advance salary') }}</th><th class="r">{{ __('Loan') }}</th><th class="r">{{ __('Other') }}</th>
                <th class="r">{{ __('Remaining salary') }}</th><th class="r">{{ __('Already taken') }}</th><th class="r">{{ __('Give now') }}</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($lines as $line)
                <tr data-pay-row data-net="{{ $line->net_pay }}" @if ($line->employee?->is_foreman) data-foreman="{{ $line->employee_id }}" @elseif ($line->employee?->foreman_id) data-team="{{ $line->employee->foreman_id }}" @endif>
                    <td><b>{{ $line->employee->localName() }}</b><div class="meta">{{ $line->employee->code }} · {{ $line->employee->is_foreman ? __('Foreman') : ($line->employee->foreman ? __('Under :name', ['name' => $line->employee->foreman->name]) : __('Rs :n/day', ['n' => number_format($line->dailyRate())])) }}</div>
                        @if ($editable)<input class="select" type="text" name="lines[{{ $line->id }}][note]" value="{{ old("lines.{$line->id}.note", $line->note) }}" placeholder="{{ __('Note') }}"  aria-label="{{ __('Note') }}">@elseif ($line->note)<div class="meta">{{ $line->note }}</div>@endif
                    </td>
                    <td class="r num">{{ number_format($line->monthly_salary) }}</td>
                    @php $daysPaid = $line->present_days + (($rules['unmarked_as'] ?? 'present') === 'absent' ? 0 : $line->unmarked_days); @endphp
                    <td class="r num">{{ number_format($line->dailyRate()) }}<div class="meta">{{ __(':s ÷ :d days', ['s' => number_format($line->monthly_salary), 'd' => $line->days_in_month]) }}</div></td>
                    <td class="r num"><b>{{ \App\Models\InventoryItem::qty($daysPaid) }}</b><div class="meta">{{ __(':p present', ['p' => \App\Models\InventoryItem::qty($line->present_days)]) }} · {{ __(':l leave, :o off', ['l' => \App\Models\InventoryItem::qty($line->leave_days), 'o' => \App\Models\InventoryItem::qty($line->off_days)]) }}@if ($line->unmarked_days) · <span style="color:var(--warn)">{{ __(':n unmarked', ['n' => $line->unmarked_days]) }}</span>@endif</div></td>
                    <td class="r num"><b>{{ number_format($line->monthly_salary - $line->absence_deduction) }}</b><div class="meta">{{ __(':a absent', ['a' => \App\Models\InventoryItem::qty($line->absent_days)]) }}</div></td>
                    <td class="r num">+{{ number_format($line->overtime_amount) }}<div class="meta">{{ \App\Models\InventoryItem::qty($line->overtime_hours) }} h</div></td>
                    <td class="r">@if ($editable)<input class="select pay-in" type="number" min="0" step="10" name="lines[{{ $line->id }}][bonus]" value="{{ old("lines.{$line->id}.bonus", $line->bonus) }}"  aria-label="{{ __('Bonus') }}">@else<span class="num">{{ number_format($line->bonus) }}</span>@endif</td>
                    <td class="r num">−{{ number_format($line->advances_deducted) }}</td>
                    <td class="r">@if ($editable)<input class="select pay-in" type="number" min="0" step="10" name="lines[{{ $line->id }}][loan_deducted]" value="{{ old("lines.{$line->id}.loan_deducted", $line->loan_deducted) }}"  aria-label="{{ __('Loan instalment') }}">@else<span class="num">−{{ number_format($line->loan_deducted) }}</span>@endif</td>
                    <td class="r">@if ($editable)<input class="select pay-in" type="number" min="0" step="10" name="lines[{{ $line->id }}][other_deduction]" value="{{ old("lines.{$line->id}.other_deduction", $line->other_deduction) }}"  aria-label="{{ __('Other deduction') }}">@else<span class="num">−{{ number_format($line->other_deduction) }}</span>@endif</td>
                    <td class="r num"><b @style(['color:var(--bad)' => $line->net_pay < 0])>Rs {{ number_format($line->net_pay) }}</b></td>
                    <td class="r">
                        @if ($line->employee?->is_foreman)
                            <span class="num" data-kept>{{ number_format($line->taken_from_foreman) }}</span><div class="meta">{{ __('kept of the cash') }}</div>
                        @elseif ($editable)
                            <input class="select pay-in" type="number" min="0" step="10" name="lines[{{ $line->id }}][taken_from_foreman]" value="{{ old("lines.{$line->id}.taken_from_foreman", $line->taken_from_foreman ?: '') }}" data-taken aria-label="{{ __('Already taken by :name', ['name' => $line->employee->name]) }}">
                            @error("lines.{$line->id}.taken_from_foreman")<div class="err">{{ $message }}</div>@enderror
                        @else
                            <span class="num">−{{ number_format($line->taken_from_foreman) }}</span>
                        @endif
                    </td>
                    <td class="r num"><b data-give>Rs {{ number_format($line->giveNow()) }}</b></td>
                    <td class="r"><a class="btn sm" href="{{ route('payroll.slips', [$run, 'line' => $line->id]) }}" aria-label="{{ __('Salary slip') }}"><x-icon name="printer" /></a></td>
                </tr>
            @empty
                <tr><td colspan="14"><div class="empty"><b>{{ __('No staff on the books this month') }}</b></div></td></tr>
            @endforelse
            </tbody>
            @if ($lines->isNotEmpty())
                <tfoot><tr>
                    <td>{{ __('Total') }}</td><td class="r num">{{ number_format($totals['salary']) }}</td><td></td><td></td>
                    <td class="r num">{{ number_format($totals['salary'] - $totals['absence']) }}</td><td class="r num">+{{ number_format($totals['overtime']) }}</td>
                    <td class="r num">{{ number_format($lines->sum('bonus')) }}</td><td class="r num">−{{ number_format($totals['advances']) }}</td>
                    <td class="r num">−{{ number_format($totals['loans']) }}</td><td class="r num">−{{ number_format($lines->sum('other_deduction')) }}</td>
                    <td class="r num">Rs {{ number_format($totals['net']) }}</td><td class="r num">−{{ number_format($totals['taken']) }}</td><td class="r num"><b id="giveTotal">Rs {{ number_format($totals['net'] - $totals['taken']) }}</b></td><td></td>
                </tr></tfoot>
            @endif
        </table></div>
        @if ($editable)<div class="form-actions"><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save changes') }}</button></div>@endif
    </form>
    @if ($editable)
        @push('scripts')
            <script>
                (() => {
                    // Give now = remaining salary − already taken; a foreman's "taken" is the cash he kept. Saving recalculates the remaining salary.
                    const rows = [...document.querySelectorAll('[data-pay-row]')], fmt = n => 'Rs ' + Math.round(n).toLocaleString('en-PK');
                    const val = el => el ? (+el.value || 0) : 0;
                    const refresh = () => {
                        const kept = {};
                        document.querySelectorAll('[data-cash]').forEach(c => { kept[c.dataset.cash] = val(c); });
                        rows.filter(r => r.dataset.team).forEach(r => { if (r.dataset.team in kept) { kept[r.dataset.team] -= val(r.querySelector('[data-taken]')); } });
                        let total = 0;
                        rows.forEach(r => {
                            let took = val(r.querySelector('[data-taken]'));
                            if (r.dataset.foreman) { took = Math.max(0, kept[r.dataset.foreman] || 0); r.querySelector('[data-kept]').textContent = took.toLocaleString('en-PK'); }
                            const give = (+r.dataset.net || 0) - took;
                            r.querySelector('[data-give]').textContent = fmt(give);
                            r.querySelector('[data-give]').style.color = give < 0 ? 'var(--bad)' : '';
                            total += give;
                        });
                        const t = document.getElementById('giveTotal'); if (t) { t.textContent = fmt(total); }
                    };
                    document.addEventListener('input', e => { if (e.target.closest('form')) { refresh(); } });
                    refresh();
                })();
            </script>
        @endpush
    @endif
</x-layouts.app>
