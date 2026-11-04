@php
    $items = $expense->items->isNotEmpty() ? $expense->items : collect([(object) ['description' => $expense->reason, 'amount' => $expense->amount]]);
@endphp
<x-layouts.app :title="$expense->receiptNumber()">
    <x-page-head :title="__('Expense receipt :no', ['no' => $expense->receiptNumber()])" :subtitle="$expense->head->localName().', '.$expense->spent_on->format('j M Y')">
        <x-slot:actions>
            <x-badge :tone="$expense->status->tone()">{{ $expense->status->label() }}</x-badge>
            @include('partials.attachment', ['path' => $expense->attachment])
            <a class="btn" href="{{ route('expenses.index') }}">{{ __('Back') }}</a>
            @if ($expense->isLinked())
                <a class="btn" href="{{ $expense->source?->cashOutUrl() }}"><x-icon name="edit" />{{ __('Edit where it was made') }}</a>
            @else
                @can('expenses.create')<a class="btn" href="{{ route('expenses.edit', $expense) }}"><x-icon name="edit" />{{ __('Edit') }}</a>@endcan
            @endif
            <button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print receipt') }}</button>
        </x-slot:actions>
    </x-page-head>

    @if ($expense->review_note)
        <div class="alert no-print"><x-icon name="alert" /><div><b>{{ __('Owner note') }}:</b> {{ $expense->review_note }}</div></div>
    @endif

    <div style="max-width:720px" dir="ltr">
        <div class="slip">
            <div class="slip-head">
                <svg class="band" viewBox="0 0 600 96" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0H600V58C470 92 330 30 200 64C120 84 50 80 0 70Z" fill="#E9EEF9"/><path d="M250 0H600V62C500 86 400 52 300 66C280 68 262 62 250 58Z" fill="#262B7E"/><path d="M0 72C60 82 130 84 200 66C330 32 470 96 600 60" stroke="#3E6FC1" stroke-width="5" fill="none"/></svg>
                <div class="lh"><x-mark :size="52" ring="#262B7E" fill="#262B7E" /><div><b>ETIHAD</b></div><div class="scr">Publishers<br>&amp; Printers</div></div>
            </div>
            <div class="slip-body">
                <div class="wm"><x-mark :size="240" ring="#262B7E" fill="#262B7E" /></div>
                <div class="slip-line" style="border-bottom:0"><span class="slip-no">EXPENSE RECEIPT</span><span class="k">رسید خرچ<small>{{ __('No. :no', ['no' => $expense->receiptNumber()]) }}</small></span></div>
                <div class="slip-line"><span class="v">{{ $expense->spent_on->format('j M Y') }}&nbsp;</span><span class="k">تاریخ<small>Date</small></span></div>
                <div class="slip-line"><span class="v">{{ $expense->paid_to }}&nbsp;</span><span class="k">ادائیگی بنام<small>Paid to</small></span></div>
                <div class="slip-line"><span class="v">{{ $expense->head->name }}{{ $expense->machine ? ' — '.$expense->machine->code : '' }}{{ $expense->category ? ' — '.$expense->category->name : '' }}&nbsp;</span><span class="k">مد<small>Head</small></span></div>
                <table style="width:100%;margin-top:14px;border-collapse:collapse;font-size:14px">
                    <thead><tr><th style="text-align:start;border-bottom:1px solid #C9CFE4;padding:6px 4px">Item</th><th style="text-align:end;border-bottom:1px solid #C9CFE4;padding:6px 4px">Amount (Rs)</th></tr></thead>
                    <tbody>
                    @foreach ($items as $item)
                        <tr><td style="padding:6px 4px;border-bottom:1px dashed #E1E5F1">{{ $item->description }}</td><td style="padding:6px 4px;text-align:end;border-bottom:1px dashed #E1E5F1;font-variant-numeric:tabular-nums">{{ number_format($item->amount) }}</td></tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td style="padding:8px 4px;font-weight:700">Total / کل رقم</td><td style="padding:8px 4px;text-align:end;font-weight:700;font-variant-numeric:tabular-nums">Rs {{ number_format($expense->amount) }}</td></tr></tfoot>
                </table>
                <div class="slip-sign">
                    <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط وصول کنندہ <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Received by</span></div></div>
                    <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط منظور کنندہ <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Approved by</span></div></div>
                </div>
            </div>
            <div class="slip-foot">{{ config('press.address') }}<br>Ph: {{ config('press.phone') }}</div>
        </div>
    </div>
</x-layouts.app>
