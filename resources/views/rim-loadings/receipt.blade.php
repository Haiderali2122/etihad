@php
    $cell = 'padding:6px 4px;border-bottom:1px dashed #E1E5F1';
    $th = 'border-bottom:1px solid #C9CFE4;padding:6px 4px';
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $range = $week->format('j M').' – '.$weekEnd->format('j M Y');
    $workTotal = (int) $lines->sum('amount');
    $advanceTotal = (int) $advances->sum('amount');
    $isWork = $kind === 'work';
    $isWeek = $kind === 'week';
    $heading = match ($kind) { 'work' => __('Younus work receipt'), 'advance' => __('Younus advance receipt'), default => __('Younus week receipt') };
@endphp
<x-layouts.app :title="$heading">
    <x-page-head :back="route('rim-loadings.index', ['week' => $week->toDateString()])" :title="$heading" :subtitle="$range">
        <x-slot:actions>
            @foreach (['work' => __('Work receipt'), 'advance' => __('Advance receipt'), 'week' => __('Week receipt')] as $k => $label)
                @if ($k !== $kind)<a class="btn" href="{{ route('rim-loadings.receipt', ['week' => $week->toDateString(), 'kind' => $k]) }}">{{ $label }}</a>@endif
            @endforeach
            <button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print receipt') }}</button>
        </x-slot:actions>
    </x-page-head>

    <div style="max-width:760px" dir="ltr">
        <div class="slip">
            <div class="slip-head">
                <svg class="band" viewBox="0 0 600 96" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0H600V58C470 92 330 30 200 64C120 84 50 80 0 70Z" fill="#E9EEF9"/><path d="M250 0H600V62C500 86 400 52 300 66C280 68 262 62 250 58Z" fill="#262B7E"/><path d="M0 72C60 82 130 84 200 66C330 32 470 96 600 60" stroke="#3E6FC1" stroke-width="5" fill="none"/></svg>
                <div class="lh"><x-mark :size="52" ring="#262B7E" fill="#262B7E" /><div><b>ETIHAD</b></div><div class="scr">Publishers<br>&amp; Printers</div></div>
            </div>
            <div class="slip-body">
                <div class="wm"><x-mark :size="240" ring="#262B7E" fill="#262B7E" /></div>
                <div class="slip-line" style="border-bottom:0"><span class="slip-no">{{ match ($kind) { 'work' => 'YOUNUS KHATA', 'advance' => 'YOUNUS ADVANCE', default => 'YOUNUS WEEK' } }}</span><span class="k">{{ match ($kind) { 'work' => 'یونس کا کھاتہ', 'advance' => 'یونس ایڈوانس', default => 'یونس ہفتہ وار حساب' } }}<small>{{ $range }}</small></span></div>
                <div class="slip-line"><span class="v">Younus&nbsp;</span><span class="k">نام<small>Name</small></span></div>
                <div class="slip-line"><span class="v">{{ $range }}&nbsp;</span><span class="k">ہفتہ<small>Week</small></span></div>

                @if ($isWork)
                    {{-- Every line of the week, however it was entered: his work only; advances have their own receipt. --}}
                    <table style="width:100%;margin-top:14px;border-collapse:collapse;font-size:13.5px">
                        <thead><tr><th style="text-align:start;{{ $th }};width:30px">#</th><th style="text-align:start;{{ $th }}">Date</th><th style="text-align:start;{{ $th }}">Work</th><th style="text-align:end;{{ $th }}">Qty</th><th style="text-align:end;{{ $th }}">Rate</th><th style="text-align:end;{{ $th }}">Amount (Rs)</th></tr></thead>
                        <tbody>
                        @forelse ($lines as $l)
                            <tr>
                                <td style="{{ $cell }}">{{ $loop->iteration }}</td>
                                <td style="{{ $cell }};white-space:nowrap">{{ $l->loaded_on->format('D j M') }}</td>
                                <td style="{{ $cell }}">{{ $l->workLabel() }}@if ($l->workOrder) · {{ $l->workOrder->number }}@endif @if ($l->details)<div style="color:#6B7390;font-size:12px">{{ $l->details }}</div>@endif</td>
                                <td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums;white-space:nowrap">{{ $num($l->rims) }} {{ $l->unit }}</td>
                                <td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ $num($l->rate_per_rim) }}</td>
                                <td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ number_format($l->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="{{ $cell }}">{{ __('No work this week') }}</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                            <tr><td colspan="5" style="padding:8px 4px;font-weight:700">Total / کل رقم</td><td style="padding:8px 4px;text-align:end;font-weight:700;font-variant-numeric:tabular-nums">Rs {{ number_format($workTotal) }}</td></tr>
                        </tfoot>
                    </table>
                @elseif ($isWeek)
                    {{-- The week as a whole: the bill, each advance with its date, and what remains. --}}
                    <table style="width:100%;margin-top:14px;border-collapse:collapse;font-size:14px">
                        <tbody>
                            <tr><td style="{{ $cell }};font-weight:700">Total week bill / ہفتے کا کل بل<div style="color:#6B7390;font-size:12px;font-weight:400">{{ trans_choice(':count work line|:count work lines', $lines->count()) }}</div></td><td style="{{ $cell }};text-align:end;font-weight:700;font-variant-numeric:tabular-nums">Rs {{ number_format($workTotal) }}</td></tr>
                            @foreach ($advances as $a)
                                <tr><td style="{{ $cell }}">Advance / ایڈوانس · {{ $a->paid_on->format('D j M') }}@if ($a->note) · {{ $a->note }}@endif</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">− Rs {{ number_format($a->amount) }}</td></tr>
                            @endforeach
                            <tr><td style="{{ $cell }}">Total advance / کل ایڈوانس</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">− Rs {{ number_format($advanceTotal) }}</td></tr>
                            @if ($paidSaturday > 0)
                                <tr><td style="{{ $cell }}">Paid on Saturday / ہفتے کو ادا</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">− Rs {{ number_format($paidSaturday) }}</td></tr>
                            @endif
                        </tbody>
                        <tfoot><tr><td style="padding:10px 4px;font-weight:800;border-top:1px solid #C9CFE4">Remaining / بقایا</td><td style="padding:10px 4px;text-align:end;font-weight:800;border-top:1px solid #C9CFE4;font-variant-numeric:tabular-nums">Rs {{ number_format($workTotal - $advanceTotal - $paidSaturday) }}</td></tr></tfoot>
                    </table>
                @else
                    {{-- Each advance with its date, and the total. --}}
                    <table style="width:100%;margin-top:14px;border-collapse:collapse;font-size:14px">
                        <thead><tr><th style="text-align:start;{{ $th }};width:30px">#</th><th style="text-align:start;{{ $th }}">Date</th><th style="text-align:start;{{ $th }}">Note</th><th style="text-align:end;{{ $th }}">Amount (Rs)</th></tr></thead>
                        <tbody>
                        @forelse ($advances as $a)
                            <tr><td style="{{ $cell }}">{{ $loop->iteration }}</td><td style="{{ $cell }}">{{ $a->paid_on->format('D j M Y') }}</td><td style="{{ $cell }}">{{ $a->note ?: '—' }}</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ number_format($a->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="4" style="{{ $cell }}">{{ __('Nothing given this week') }}</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot><tr><td colspan="3" style="padding:8px 4px;font-weight:700">Total advance / کل ایڈوانس</td><td style="padding:8px 4px;text-align:end;font-weight:700;font-variant-numeric:tabular-nums">Rs {{ number_format($advanceTotal) }}</td></tr></tfoot>
                    </table>
                @endif

                <div class="slip-sign">
                    <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط یونس <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Younus</span></div></div>
                    <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط مینیجر <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Manager</span></div></div>
                </div>
            </div>
            <div class="slip-foot">{{ config('press.address') }}<br>Ph: {{ config('press.phone') }}</div>
        </div>
    </div>
</x-layouts.app>
