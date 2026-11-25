@php
    $cell = 'padding:8px 4px;border-bottom:1px dashed #E1E5F1';
    $give = max(0, (int) $record->amount - (int) $record->taken_from_foreman);
@endphp
<x-layouts.app :title="__('Advance salary slip')">
    <x-page-head :back="route('salary-advances.mid-month', ['month' => $month->format('Y-m')])" :title="__('Advance salary slip')" :subtitle="$employee->name.' · '.$month->format('F Y')">
        <x-slot:actions><button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print receipt') }}</button></x-slot:actions>
    </x-page-head>
    <div style="max-width:620px" dir="ltr">
        <div class="slip">
            <div class="slip-head">
                <svg class="band" viewBox="0 0 600 96" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0H600V58C470 92 330 30 200 64C120 84 50 80 0 70Z" fill="#E9EEF9"/><path d="M250 0H600V62C500 86 400 52 300 66C280 68 262 62 250 58Z" fill="#262B7E"/><path d="M0 72C60 82 130 84 200 66C330 32 470 96 600 60" stroke="#3E6FC1" stroke-width="5" fill="none"/></svg>
                <div class="lh"><x-mark :size="52" ring="#262B7E" fill="#262B7E" /><div><b>ETIHAD</b></div><div class="scr">Publishers<br>&amp; Printers</div></div>
            </div>
            <div class="slip-body">
                <div class="wm"><x-mark :size="240" ring="#262B7E" fill="#262B7E" /></div>
                <div class="slip-line" style="border-bottom:0"><span class="slip-no">ADVANCE SALARY</span><span class="k">ایڈوانس تنخواہ<small>{{ $month->format('F Y') }}</small></span></div>
                <div class="slip-line"><span class="v">{{ $employee->name }} ({{ $employee->code }})&nbsp;</span><span class="k">نام<small>Name</small></span></div>
                @if ($employee->foreman)<div class="slip-line"><span class="v">{{ $employee->foreman->name }}&nbsp;</span><span class="k">فورمین<small>Foreman</small></span></div>@endif
                <div class="slip-line"><span class="v">{{ $record->given_on->format('j M Y') }}&nbsp;</span><span class="k">تاریخ<small>Date</small></span></div>
                <table style="width:100%;margin-top:14px;border-collapse:collapse;font-size:15px">
                    <tbody>
                        <tr><td style="{{ $cell }}">Monthly salary / ماہانہ تنخواہ</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">Rs {{ number_format($employee->monthly_salary) }}</td></tr>
                        <tr><td style="{{ $cell }};font-weight:700">Advance salary / ایڈوانس تنخواہ</td><td style="{{ $cell }};text-align:end;font-weight:700;font-variant-numeric:tabular-nums">Rs {{ number_format($record->amount) }}</td></tr>
                        <tr><td style="{{ $cell }}">Already taken from foreman / فورمین سے لیا</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">− Rs {{ number_format($record->taken_from_foreman) }}</td></tr>
                    </tbody>
                    <tfoot><tr><td style="padding:10px 4px;font-weight:800;border-top:1px solid #C9CFE4">Given now / ابھی دیا</td><td style="padding:10px 4px;text-align:end;font-weight:800;border-top:1px solid #C9CFE4;font-variant-numeric:tabular-nums">Rs {{ number_format($give) }}</td></tr></tfoot>
                </table>
                <div class="slip-sign">
                    <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط وصول کنندہ <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Received by</span></div></div>
                    <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط مینیجر <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Manager</span></div></div>
                </div>
            </div>
            <div class="slip-foot">{{ config('press.address') }}<br>Ph: {{ config('press.phone') }}</div>
        </div>
    </div>
</x-layouts.app>
