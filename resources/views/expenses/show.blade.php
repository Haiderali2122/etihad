@php
    $items = $expense->items->isNotEmpty() ? $expense->items : collect([(object) ['description' => $expense->reason, 'amount' => $expense->amount, 'paid_to' => $expense->paid_to, 'head' => $expense->head]]);
    $heads = $items->map(fn ($i) => ($i->head ?? $expense->head)->name)->unique()->implode(', ');
    $payees = $items->map(fn ($i) => $i->paid_to ?: $expense->paid_to)->filter(fn ($p) => filled($p) && $p !== '—')->unique()->implode(', ');
    // Quantity and rate columns only when the lines carry them (Diesel, Glue and Ink).
    $showQty = $items->contains(fn ($i) => isset($i->quantity) && $i->quantity !== null);
    $cell = 'padding:6px 4px;border-bottom:1px dashed #E1E5F1';
    $th = 'border-bottom:1px solid #C9CFE4;padding:6px 4px';
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp
<x-layouts.app :title="$expense->receiptNumber()">
    <x-page-head :title="__('Expense receipt :no', ['no' => $expense->receiptNumber()])" :subtitle="$expense->head->localName().', '.$expense->spent_on->format('j M Y')">
        <x-slot:actions>
            <x-badge :tone="$expense->status->tone()">{{ $expense->status->label() }}</x-badge>
            @include('partials.attachment', ['path' => $expense->attachment])
            <a class="btn" href="{{ route('expenses.index') }}">{{ __('Back') }}</a>
            @if ($expense->isLinked())
                <a class="btn" href="{{ $expense->source?->cashOutUrl() }}"><x-icon name="edit" />{{ __('Edit where it was made') }}</a>
                @if ($expense->source)@can('expenses.create')<x-delete-button :action="route('expenses.destroy', $expense)" :confirm="__('Delete this :kind? It is also removed from its own tab.', ['kind' => mb_strtolower($expense->source->cashOutSource())])" :label="__('Delete')" />@endcan @endif
            @else
                @can('expenses.create')<a class="btn" href="{{ route('expenses.edit', $expense) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                    <x-delete-button :action="route('expenses.destroy', $expense)" :confirm="__('Delete expense :no?', ['no' => $expense->receiptNumber()])" :label="__('Delete')" />@endcan
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
                @if ($payees)<div class="slip-line"><span class="v">{{ $payees }}&nbsp;</span><span class="k">ادائیگی بنام<small>Paid to</small></span></div>@endif
                <div class="slip-line"><span class="v">{{ $heads }}{{ $expense->machine ? ' — '.$expense->machine->code : '' }}{{ $expense->category ? ' — '.$expense->category->name : '' }}&nbsp;</span><span class="k">مد<small>Head</small></span></div>
                @if ($expense->isLinked() && $expense->source)
                    <div class="slip-line"><span class="v">{{ $expense->source->cashOutSource() }}&nbsp;</span><span class="k">ذریعہ<small>Recorded as</small></span></div>
                @endif
                <table style="width:100%;margin-top:14px;border-collapse:collapse;font-size:14px">
                    <thead><tr><th style="text-align:start;{{ $th }};width:36px">#</th><th style="text-align:start;{{ $th }}">Item</th>@if ($showQty)<th style="text-align:end;{{ $th }}">Qty</th><th style="text-align:end;{{ $th }}">Rate</th>@endif<th style="text-align:end;{{ $th }}">Amount (Rs)</th></tr></thead>
                    <tbody>
                    @foreach ($items as $item)
                        <tr><td style="{{ $cell }}">{{ $loop->iteration }}</td><td style="{{ $cell }}">{{ $item->description ?: ($item->head ?? $expense->head)->name }}</td>@if ($showQty)<td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ isset($item->quantity) && $item->quantity !== null ? $num($item->quantity) : '' }}</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ isset($item->rate) && $item->rate !== null ? $num($item->rate) : '' }}</td>@endif<td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ number_format($item->amount) }}</td></tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="{{ $showQty ? 4 : 2 }}" style="padding:8px 4px;font-weight:700">Total / کل رقم</td><td style="padding:8px 4px;text-align:end;font-weight:700;font-variant-numeric:tabular-nums">Rs {{ number_format($expense->amount) }}</td></tr></tfoot>
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
