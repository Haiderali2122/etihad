<x-layouts.app :title="__('Salary slips')">
    <x-page-head :title="__('Salary slips — :month', ['month' => $run->label()])" :subtitle="trans_choice(':count slip|:count slips', $lines->count())">
        <x-slot:actions>
            <a class="btn" href="{{ route('payroll.show', $run) }}">{{ __('Back') }}</a>
            <button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-head>
    @if ($run->isDraft())<div class="alert no-print"><x-icon name="alert" /><div>{{ __('This payroll is still a draft; figures may change until it is finalized.') }}</div></div>@endif

    <div class="slips">
        @foreach ($lines as $line)
            @php $qty = fn ($v) => \App\Models\InventoryItem::qty($v); @endphp
            <div class="slip payslip" dir="ltr">
                <div class="slip-head">
                    <svg class="band" viewBox="0 0 600 96" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0H600V58C470 92 330 30 200 64C120 84 50 80 0 70Z" fill="#E9EEF9"/><path d="M250 0H600V62C500 86 400 52 300 66C280 68 262 62 250 58Z" fill="#262B7E"/><path d="M0 72C60 82 130 84 200 66C330 32 470 96 600 60" stroke="#3E6FC1" stroke-width="5" fill="none"/></svg>
                    <div class="lh"><x-mark :size="52" ring="#262B7E" fill="#262B7E" /><div><b>ETIHAD</b></div><div class="scr">Salary slip<br>{{ $run->label() }}</div></div>
                </div>
                <div class="slip-body">
                    <div class="slip-two">
                        <div class="slip-line"><span class="v">{{ $line->employee->localName() }}</span><span class="k">نام<small>Name</small></span></div>
                        <div class="slip-line"><span class="v">{{ $line->employee->code }}</span><span class="k">نمبر<small>Employee no.</small></span></div>
                    </div>
                    <div class="slip-two">
                        <div class="slip-line"><span class="v">{{ $line->employee->designation }}</span><span class="k">عہدہ<small>Designation</small></span></div>
                        <div class="slip-line"><span class="v">{{ $run->pay_date?->format('j M Y') }}</span><span class="k">تاریخ ادائیگی<small>Pay date</small></span></div>
                    </div>
                    <table class="pay-table">
                        <tr><td>Monthly salary <span class="ur">تنخواہ</span></td><td>{{ $line->days_in_month }} days · Rs {{ number_format($line->dailyRate()) }}/day</td><td class="r">{{ number_format($line->monthly_salary) }}</td></tr>
                        <tr><td>Overtime <span class="ur">اوور ٹائم</span></td><td>{{ $qty($line->overtime_hours) }} hours</td><td class="r">+ {{ number_format($line->overtime_amount) }}</td></tr>
                        @if ($line->bonus)<tr><td>Bonus <span class="ur">بونس</span></td><td></td><td class="r">+ {{ number_format($line->bonus) }}</td></tr>@endif
                        <tr><td>Absence <span class="ur">غیر حاضری</span></td><td>{{ $qty($line->absent_days) }} days · present {{ $qty($line->present_days) }}, leave {{ $qty($line->leave_days) }}</td><td class="r">− {{ number_format($line->absence_deduction) }}</td></tr>
                        <tr><td>Advances <span class="ur">ایڈوانس</span></td><td></td><td class="r">− {{ number_format($line->advances_deducted) }}</td></tr>
                        <tr><td>Loan instalment <span class="ur">قسط قرض</span></td><td>@if ($line->loan_deducted) balance after: Rs {{ number_format(max(0, $line->employee->loanBalance() - ($run->isDraft() ? $line->loan_deducted : 0))) }}@endif</td><td class="r">− {{ number_format($line->loan_deducted) }}</td></tr>
                        @if ($line->other_deduction)<tr><td>Other deductions <span class="ur">دیگر کٹوتی</span></td><td>{{ $line->note }}</td><td class="r">− {{ number_format($line->other_deduction) }}</td></tr>@endif
                        <tr class="net"><td>Net pay <span class="ur">خالص تنخواہ</span></td><td>rounded to nearest Rs 10</td><td class="r">Rs {{ number_format($line->net_pay) }}</td></tr>
                    </table>
                    <div class="slip-sign">
                        <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط ملازم <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Employee</span></div></div>
                        <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط منظور کنندہ <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Approved by</span></div></div>
                    </div>
                </div>
                <div class="slip-foot">{{ config('press.address') }} · Ph: {{ config('press.phone') }}</div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
