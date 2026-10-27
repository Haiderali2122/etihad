<x-layouts.app :title="__('Loan statement')">
    <x-page-head :title="$loan->isCleared() ? __('Loan clearance slip') : __('Loan statement')" :subtitle="$loan->employee->name.' · '.__('Rs :n given on :date', ['n' => number_format($loan->amount), 'date' => $loan->issued_on->format('j M Y')])">
        <x-slot:actions>
            @if ($loan->isCleared())<x-badge tone="good">{{ __('Cleared') }}</x-badge>@else<x-badge :tone="$loan->status->tone()">{{ $loan->status->label() }}</x-badge>@endif
            <a class="btn" href="{{ route('loans.index') }}">{{ __('Back') }}</a>
            <button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif
    @if ($loan->review_note)<div class="alert"><x-icon name="alert" /><div><b>{{ __('Owner note') }}:</b> {{ $loan->review_note }}</div></div>@endif

    <div class="grid g-main">
        @include('advances.receipt', [
            'title' => $loan->isCleared() ? 'LOAN CLEARANCE' : 'LOAN STATEMENT', 'titleUr' => $loan->isCleared() ? 'کلیئرنس قرض' : 'گوشوارہ قرض',
            'no' => 'LN-'.str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT),
            'lines' => [
                ['نام', 'Name', $loan->employee->name.' ('.$loan->employee->code.')'],
                ['تاریخ', 'Date given', $loan->issued_on->format('j M Y')],
                ['رقم قرض', 'Loan amount', 'Rs '.number_format($loan->amount)],
                ['ماہانہ قسط', 'Monthly instalment', 'Rs '.number_format($loan->monthly_instalment)],
                ['ادا شدہ', 'Repaid', 'Rs '.number_format($loan->repaid())],
                ['بقایا', 'Balance', $loan->isCleared() ? 'NIL — fully cleared' : 'Rs '.number_format($loan->balance())],
            ],
        ])
        <div style="display:flex;flex-direction:column;gap:18px">
            <div class="card">
                <div class="card-h"><div><h3>{{ __('Repayments') }}</h3></div></div>
                <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('How') }}</th><th class="r">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                    @forelse ($loan->repayments as $r)
                        <tr><td>{{ $r->paid_on->format('j M Y') }}</td><td>{{ $r->payrollRun ? __('Salary :month', ['month' => $r->payrollRun->label()]) : ($r->note ?: __('Cash')) }}</td><td class="r num"><b>Rs {{ number_format($r->amount) }}</b></td></tr>
                    @empty
                        <tr><td colspan="3"><div class="empty">{{ __('Nothing repaid yet') }}</div></td></tr>
                    @endforelse
                    </tbody>
                </table></div></div>
            </div>
            @can('advances.create')
                @if (! $loan->isCleared() && $loan->status->value !== 'rejected')
                    <form method="POST" action="{{ route('loans.repay', $loan) }}" class="card no-print">
                        @csrf
                        <div class="card-h"><div><h3>{{ __('Cash repayment') }}</h3><div class="sub">{{ __('Paid back outside payroll') }}</div></div></div>
                        <div class="card-b"><div class="form" style="grid-template-columns:minmax(0,1fr)">
                            <x-field name="amount" type="number" min="1" :max="$loan->balance()" :label="__('Amount (Rs)')" required />
                            <x-field name="paid_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                            <x-field name="note" :label="__('Note')" />
                            <button class="btn primary" type="submit" style="justify-content:center"><x-icon name="check" />{{ __('Record repayment') }}</button>
                        </div></div>
                    </form>
                @endif
            @endcan
        </div>
    </div>
</x-layouts.app>
