@php
    $category = $first->item?->category;
    $total = (int) $purchases->sum('amount');
    $cell = 'padding:6px 4px;border-bottom:1px dashed #E1E5F1';
    $head = 'border-bottom:1px solid #C9CFE4;padding:6px 4px';
@endphp
<x-layouts.app :title="$receiptNo">
    <x-page-head :back="$category ? route('inventory.index', ['tab' => 'material-'.$category->id]) : route('inventory.index', ['tab' => 'purchases'])" :title="__('Purchase receipt :no', ['no' => $receiptNo])" :subtitle="($category?->localName() ?? '').', '.$first->purchased_on->format('j M Y')">
        <x-slot:actions>
            <x-badge :tone="$first->status->tone()">{{ $first->status->label() }}</x-badge>
            @include('partials.attachment', ['path' => $first->attachment])
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
                <div class="slip-line" style="border-bottom:0"><span class="slip-no">PURCHASE RECEIPT</span><span class="k">رسید خریداری<small>{{ __('No. :no', ['no' => $receiptNo]) }}</small></span></div>
                <div class="slip-line"><span class="v">{{ $first->purchased_on->format('j M Y') }}&nbsp;</span><span class="k">تاریخ<small>Date</small></span></div>
                <div class="slip-line"><span class="v">{{ $first->party?->name }}&nbsp;</span><span class="k">فروخت کنندہ<small>Supplier</small></span></div>
                <div class="slip-line"><span class="v">{{ $category?->name }}{{ $first->bill_no ? ' — bill '.$first->bill_no : '' }}&nbsp;</span><span class="k">مد<small>Material</small></span></div>
                <table style="width:100%;margin-top:14px;border-collapse:collapse;font-size:14px">
                    <thead><tr><th style="text-align:start;{{ $head }}">Item</th><th style="text-align:end;{{ $head }}">Qty</th><th style="text-align:end;{{ $head }}">Rate</th><th style="text-align:end;{{ $head }}">Amount (Rs)</th></tr></thead>
                    <tbody>
                    @foreach ($purchases as $p)
                        <tr><td style="{{ $cell }}">{{ $p->item?->name }}</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ \App\Models\InventoryItem::qty($p->quantity) }} {{ $p->item?->unit }}</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ number_format((float) $p->rate, 2) }}</td><td style="{{ $cell }};text-align:end;font-variant-numeric:tabular-nums">{{ number_format($p->amount) }}</td></tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="3" style="padding:8px 4px;font-weight:700">Total / کل رقم</td><td style="padding:8px 4px;text-align:end;font-weight:700;font-variant-numeric:tabular-nums">Rs {{ number_format($total) }}</td></tr></tfoot>
                </table>
                @if ($first->note)<div class="slip-line"><span class="v">{{ $first->note }}&nbsp;</span><span class="k">نوٹ<small>Note</small></span></div>@endif
                <div class="slip-sign">
                    <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط وصول کنندہ <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Received by</span></div></div>
                    <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط منظور کنندہ <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Approved by</span></div></div>
                </div>
            </div>
            <div class="slip-foot">{{ config('press.address') }}<br>Ph: {{ config('press.phone') }}</div>
        </div>
    </div>
</x-layouts.app>
