@props(['icon', 'label', 'tone' => '', 'foot' => null])
<div class="card kpi">
    <div class="kpi-top"><span class="kpi-label">{{ $label }}</span><span class="kpi-ic {{ $tone }}"><x-icon :name="$icon" /></span></div>
    <div class="kpi-val num">{{ $slot }}</div>
    @if ($foot)
        <div class="kpi-foot"><span>{{ $foot }}</span></div>
    @endif
</div>
