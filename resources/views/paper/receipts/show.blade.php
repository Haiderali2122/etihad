@php
    $line = fn (string $ur, string $en, ?string $value) => '<div class="slip-line"><span class="v">'.e($value ?: '').'&nbsp;</span><span class="k">'.$ur.'<small>'.e($en).'</small></span></div>';
@endphp
<x-layouts.app :title="__('Slip :no', ['no' => $receipt->slip_no])">
    <x-page-head :title="__('Receipt slip No. :no', ['no' => $receipt->slip_no])" :subtitle="$receipt->party->name.', '.$receipt->received_at->format('j M Y')">
        <x-slot:actions>
            <x-badge :tone="$receipt->status->tone()">{{ $receipt->status->label() }}</x-badge>
            @include('partials.attachment', ['path' => $receipt->attachment])
            <a class="btn" href="{{ route('paper.index', ['tab' => 'receipts']) }}">{{ __('Back') }}</a>
            <button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print slip') }}</button>
        </x-slot:actions>
    </x-page-head>

    @if ($receipt->review_note)
        <div class="alert no-print"><x-icon name="alert" /><div><b>{{ __('Owner note') }}:</b> {{ $receipt->review_note }}</div></div>
    @endif

    <div style="max-width:720px" dir="ltr">
        <div class="slip">
            <div class="slip-head">
                <svg class="band" viewBox="0 0 600 96" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0H600V58C470 92 330 30 200 64C120 84 50 80 0 70Z" fill="#E9EEF9"/><path d="M250 0H600V62C500 86 400 52 300 66C280 68 262 62 250 58Z" fill="#262B7E"/><path d="M0 72C60 82 130 84 200 66C330 32 470 96 600 60" stroke="#3E6FC1" stroke-width="5" fill="none"/></svg>
                <div class="lh"><x-mark :size="52" ring="#262B7E" fill="#262B7E" /><div><b>ETIHAD</b></div><div class="scr">Publishers<br>&amp; Printers</div></div>
            </div>
            <div class="slip-body">
                <div class="wm"><x-mark :size="240" ring="#262B7E" fill="#262B7E" /></div>
                <div class="slip-two">{!! $line('تاریخ', 'Date', $receipt->received_at->format('j M Y')) !!}{!! $line('ٹرک نمبر', 'Truck no.', $receipt->truck_no) !!}</div>
                <div class="slip-two">{!! $line('ٹائم', 'Time', $receipt->received_at->format('H:i')) !!}<div class="slip-line"><span class="slip-no">{{ $receipt->slip_no }}</span><span class="k">نمبر شمار<small>Serial no.</small></span></div></div>
                {!! $line('نام پارٹی', 'Party', $receipt->party->name) !!}
                {!! $line('کاغذ کی قسم', 'Paper type', $receipt->paperType->name) !!}
                <div class="slip-two">{!! $line('کاغذ سائز', 'Size', $receipt->paperType->size.($receipt->paperType->gsm ? ' × '.$receipt->paperType->gsm : '')) !!}{!! $line('تعداد رم / پیکٹ', 'Rims / packets', number_format($receipt->quantity)) !!}</div>
                <div class="slip-sign">
                    <div><div class="sig">{{ $receipt->received_by }}</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط وصول کنندہ <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Received by</span></div></div>
                    <div class="slip-note"><span class="k">نوٹ</span>{{ __('Unloaded in :store', ['store' => $receipt->godown?->localName() ?? '—']) }}@if ($receipt->note)<br>{{ $receipt->note }}@endif</div>
                </div>
            </div>
            <div class="slip-foot">{{ config('press.address') }}<br>Ph: {{ config('press.phone') }}</div>
        </div>
    </div>
</x-layouts.app>
