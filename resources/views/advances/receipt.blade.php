{{-- Letterhead receipt used for advance and loan clearance slips. Pass $title, $no and $lines (label => value). --}}
<div style="max-width:720px" dir="ltr">
    <div class="slip">
        <div class="slip-head">
            <svg class="band" viewBox="0 0 600 96" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0H600V58C470 92 330 30 200 64C120 84 50 80 0 70Z" fill="#E9EEF9"/><path d="M250 0H600V62C500 86 400 52 300 66C280 68 262 62 250 58Z" fill="#262B7E"/><path d="M0 72C60 82 130 84 200 66C330 32 470 96 600 60" stroke="#3E6FC1" stroke-width="5" fill="none"/></svg>
            <div class="lh"><x-mark :size="52" ring="#262B7E" fill="#262B7E" /><div><b>ETIHAD</b></div><div class="scr">Publishers<br>&amp; Printers</div></div>
        </div>
        <div class="slip-body">
            <div class="wm"><x-mark :size="240" ring="#262B7E" fill="#262B7E" /></div>
            <div class="slip-line" style="border-bottom:0"><span class="slip-no">{{ $title }}</span><span class="k">{{ $titleUr }}<small>{{ __('No. :no', ['no' => $no]) }}</small></span></div>
            @foreach ($lines as [$ur, $en, $value])
                <div class="slip-line"><span class="v">{{ $value }}&nbsp;</span><span class="k">{{ $ur }}<small>{{ $en }}</small></span></div>
            @endforeach
            <div class="slip-sign">
                <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط وصول کنندہ <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Received by</span></div></div>
                <div><div class="sig" style="font-family:inherit;font-size:14px">&nbsp;</div><div style="font-family:var(--urdu);font-size:13px;margin-top:4px">دستخط منظور کنندہ <span style="font-family:var(--ui);font-size:11px;color:var(--faint)">Approved by</span></div></div>
            </div>
        </div>
        <div class="slip-foot">{{ config('press.address') }}<br>Ph: {{ config('press.phone') }}</div>
    </div>
</div>
