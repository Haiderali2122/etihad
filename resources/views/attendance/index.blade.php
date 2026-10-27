@php
    $prev = $month->copy()->subMonthNoOverflow()->format('Y-m');
    $next = $month->copy()->addMonthNoOverflow();
@endphp
<x-layouts.app :title="__('Attendance')">
    <x-page-head :title="__('Attendance')" :subtitle="__('The month at a glance. Click a day number to mark or correct that day for everyone.')">
        <x-slot:actions>
            @can('attendance.manage')
                <a class="btn" href="{{ route('attendance.import') }}"><x-icon name="download" />{{ __('Import from device') }}</a>
                <a class="btn primary" href="{{ route('attendance.day') }}"><x-icon name="fingerprint" />{{ __('Mark today') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-head>

    <div class="card">
        <div class="toolbar">
            <div style="display:flex;align-items:center;gap:8px">
                <a class="btn sm" href="{{ route('attendance.index', ['month' => $prev]) }}" aria-label="{{ __('Previous month') }}">‹</a>
                <h3 style="margin:0;min-width:140px;text-align:center">{{ $month->translatedFormat('F Y') }}</h3>
                @if ($next->lte(now()))<a class="btn sm" href="{{ route('attendance.index', ['month' => $next->format('Y-m')]) }}" aria-label="{{ __('Next month') }}">›</a>@endif
            </div>
            <div class="legend">
                <span><i style="background:#7FA0DE"></i>{{ __('Present') }}</span><span><i style="background:var(--indigo)"></i>{{ __('Overtime') }}</span>
                <span><i style="background:#E79A8C"></i>{{ __('Absent') }}</span><span><i style="background:var(--royal-soft)"></i>{{ __('Leave') }}</span>
                <span><i style="background:var(--line)"></i>{{ __('Off') }}</span><span><i style="border:1px dashed var(--faint)"></i>{{ __('Not marked') }}</span>
            </div>
        </div>
        <div class="att-grid-wrap">
            <table class="att-grid no-stack">
                <thead><tr>
                    <th class="att-name">{{ __('Employee') }}</th>
                    @foreach ($days as $d)
                        @php $date = $month->copy()->day($d); @endphp
                        <th @class(['wk' => $date->isSunday()])>
                            @can('attendance.manage')
                                @if ($date->lte(today()))<a href="{{ route('attendance.day', ['date' => $date->toDateString()]) }}" title="{{ $date->translatedFormat('l j F') }}">{{ $d }}</a>@else{{ $d }}@endif
                            @else{{ $d }}@endcan
                        </th>
                    @endforeach
                    <th class="r">{{ __('P') }}</th><th class="r">{{ __('A') }}</th><th class="r">{{ __('OT h') }}</th>
                </tr></thead>
                <tbody>
                @forelse ($employees as $e)
                    @php $byDay = $e->attendances->keyBy(fn ($a) => $a->date->day); @endphp
                    <tr>
                        <td class="att-name"><a href="{{ route('employees.show', $e) }}" style="color:inherit;text-decoration:none"><b>{{ $e->localName() }}</b></a><div class="meta">{{ $e->designation }}</div></td>
                        @foreach ($days as $d)
                            @php $a = $byDay->get($d); @endphp
                            <td><i title="{{ $d }}: {{ $a?->status->label() ?? __('Not marked') }}{{ $a && $a->overtime_minutes ? ' · '.__('OT :h h', ['h' => round($a->overtime_minutes / 60, 1)]) : '' }}" @class([
                                'c', 'p' => $a?->status->value === 'present' && ! $a->overtime_minutes, 'ot' => $a?->status->value === 'present' && $a->overtime_minutes,
                                'a' => $a?->status->value === 'absent', 'l' => $a?->status->value === 'leave', 'o' => $a?->status->value === 'off',
                                'n' => ! $a && $d <= $lastDay, 'f' => ! $a && $d > $lastDay,
                            ])></i></td>
                        @endforeach
                        <td class="r num">{{ $e->attendances->where('status.value', 'present')->count() }}</td>
                        <td class="r num">{{ $e->attendances->where('status.value', 'absent')->count() }}</td>
                        <td class="r num">{{ \App\Models\InventoryItem::qty($e->attendances->sum('overtime_minutes') / 60) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($days) + 4 }}"><div class="empty"><b>{{ __('No active staff') }}</b></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
