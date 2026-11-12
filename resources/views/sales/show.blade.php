@php
    $cell = 'padding:6px 4px;border-bottom:1px dashed #E1E5F1';
    $th = 'border-bottom:1px solid #C9CFE4;padding:6px 4px';
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp
<x-layouts.app :title="$sale->receiptNumber()">
    <x-page-head :back="route('inventory.index', ['tab' => 'material-'.$sale->inventory_category_id])" :title="__('Sale slip :no', ['no' => $sale->receiptNumber()])" :subtitle="$sale->category->localName().', '.$sale->sold_on->format('j M Y')">
        <x-slot:actions>
            @include('partials.attachment', ['path' => $sale->attachment])
            @can('expenses.create')<x-delete-button :action="route('records.destroy', ['sale', $sale->id])" :confirm="__('Delete sale :no?', ['no' => $sale->receiptNumber()])" :label="__('Delete')" :redirect="route('inventory.index', ['tab' => 'material-'.$sale->inventory_category_id])" />@endcan
            <button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print receipt') }}</button>
        </x-slot:actions>
    </x-page-head>

    <div style="max-width:720px" dir="ltr">
        <div class="slip">
            <div class="slip-head">
                <svg class="band" viewBox="0 0 600 96" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0H600V58C470 92 330 30 200 64C120 84 50 80 0 70Z" fill="#E9EEF9"/><path d="M250 0H600V62C500 86 400 52 300 66C280 68 262 62 250 58Z" fill="#262B7E"/><path d="M0 72C60 82 130 84 200 66C330 32 470 96 600 60" stroke="#3E6FC1" stroke-width="5" fill="none"/></svg>
                <div class="lh"><x-mark :size="52" ring="#262B7E" fill="#262B7E" /><div><b>ETIHAD</b></div><div class="scr">Publishers<br>&amp; Printers</div></div>
            </div>
            <div class="slip-body">
                <div class="wm"><x-mark :size="240" ring="#262B7E" fill="#262B7E" /></div>
                <div class="slip-line" style="border-bottom:0"><span class="slip-no">SALE SLIP</span><span class="k">رسید فروخت<small>{{ __('No. :no', ['no' => $sale->receiptNumber()]) }}</small></span></div>
                <div class="slip-line"><span class="v">{{ $sale->sold_on->format('j M Y') }}&nbsp;</span><span class="k">تاریخ<small>Date</small></span></div>
                <div class="slip-line"><span class="v">{{ $sale->category->name }}&nbsp;</span><span class="k">مد<small>Sold</small></span></div>
                <table style="width:100%;margin-top:14px;border-collapse:collapse;font-size:14px">
                    <thead><tr><th style="text-align:start;{{ $th }};width:36px">#</th><th style="text-align:start;{{ $th }}">Item</th><th style="text-align:end;{{ $th }}">Qty</th><th style="text-align:end;{{ $th }}">Rate</th><th style="text-align:end;{{ $th }}">Amount (Rs)</th></tr></thead>
                    <tbody>
                    @foreach ($sale->items as $item)
                        <tr><td style="{{ $cell }}">{{ $loop->iteration }}</td><td style="{{ $cell }}">{{ $item->description }}</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ $num($item->quantity) }}</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ $num($item->rate) }}</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ number_format($item->amount) }}</td></tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="4" style="padding:8px 4px;font-weight:700">Total / کل رقم</td><td style="padding:8px 4px;text-align:end;font-weight:700;font-variant-numeric:tabular-nums">Rs {{ number_format($sale->total) }}</td></tr></tfoot>
                </table>
                <div class="slip-sign">
                    <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط خریدار <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Buyer</span></div></div>
                    <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط منظور کنندہ <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Approved by</span></div></div>
                </div>
            </div>
            <div class="slip-foot">{{ config('press.address') }}<br>Ph: {{ config('press.phone') }}</div>
        </div>
    </div>
</x-layouts.app>
