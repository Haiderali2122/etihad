<x-layouts.app :title="__('Mark attendance')">
    <x-page-head :title="__('Attendance for :date', ['date' => $date->translatedFormat('l j F Y')])" :subtitle="__('Pick each person\'s status. Overtime counts only for people marked present.')">
        <x-slot:actions>
            <a class="btn" href="{{ route('attendance.day', ['date' => $date->copy()->subDay()->toDateString()]) }}" aria-label="{{ __('Previous day') }}">‹ {{ $date->copy()->subDay()->format('j M') }}</a>
            <form method="GET" class="filters" style="align-items:center">
                <input class="select" type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" aria-label="{{ __('Date') }}" onchange="this.form.submit()">
            </form>
            @if ($date->lt(today()))<a class="btn" href="{{ route('attendance.day', ['date' => $date->copy()->addDay()->toDateString()]) }}" aria-label="{{ __('Next day') }}">{{ $date->copy()->addDay()->format('j M') }} ›</a>@endif
            @can('attendance.manage')<a class="btn" href="{{ route('attendance.machine') }}"><x-icon name="fingerprint" />{{ __('Attendance machine') }}</a>@endcan
            <a class="btn primary" href="{{ route('attendance.index', ['month' => $date->format('Y-m')]) }}"><x-icon name="cal" />{{ __('Month') }}</a>
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    @php
        $marked = $records->filter(fn ($r) => $employees->contains('id', $r->employee_id));
        $present = $marked->filter(fn ($r) => $r->status->value === 'present')->count();
        $absent = $marked->filter(fn ($r) => $r->status->value === 'absent')->count();
        $lateCount = $marked->filter(fn ($r) => $r->lateMinutes() > 0)->count();
        $otCount = $marked->filter(fn ($r) => $r->overtime_minutes > 0)->count();
        // Everyone without a punch or a mark counts as absent, with those marked absent.
        $noPunch = $employees->count() - $marked->count();
    @endphp
    {{-- Click a card to show only those people below; click it again to show everyone. --}}
    <div class="grid g4 att-filters">
        <x-kpi icon="check" tone="good" :label="__('Present')" :foot="__('of :n staff', ['n' => $employees->count()])" data-filter="present" role="button" tabindex="0">{{ $present }}</x-kpi>
        <x-kpi icon="x" tone="bad" :label="__('Absent')" :foot="__(':a marked absent · :n no punch', ['a' => $absent, 'n' => $noPunch])" data-filter="absent" role="button" tabindex="0">{{ $absent + $noPunch }}</x-kpi>
        <x-kpi icon="clock" tone="warn" :label="__('Late')" :foot="__('Shift :from to :to · late after :grace min', ['from' => sprintf('%d:%02d', intdiv($shift[0], 60), $shift[0] % 60), 'to' => sprintf('%d:%02d', intdiv($shift[1], 60), $shift[1] % 60), 'grace' => $shift[2]])" data-filter="late" role="button" tabindex="0">{{ $lateCount }}</x-kpi>
        <x-kpi icon="plus" tone="good" :label="__('Overtime')" :foot="trans_choice(':count person|:count people', $otCount)" data-filter="overtime" role="button" tabindex="0">{{ \App\Models\Attendance::hm((int) $marked->sum('overtime_minutes')) }}</x-kpi>
    </div>

    <form method="POST" action="{{ route('attendance.move-shift') }}" id="shiftForm">@csrf</form>
    <form method="POST" action="{{ route('attendance.save-day') }}" class="card">
        @csrf @method('PUT')
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <div class="toolbar">
            <span class="muted">{{ __(':n staff · :m already marked', ['n' => $employees->count(), 'm' => $records->count()]) }}@if ($date->isSunday()) · <b>{{ __('Sunday: paid holiday; time worked is overtime') }}</b>@endif</span>
            @can('attendance.manage')
                {{-- Tick people below, then move them to a shift. --}}
                <div class="filters" style="gap:6px">
                    <span class="muted" id="tickedCount"></span>
                    <button type="submit" form="shiftForm" name="shift" value="day" class="btn sm"><x-icon name="swap" />{{ __('Move to day shift') }}</button>
                    <button type="submit" form="shiftForm" name="shift" value="night" class="btn sm"><x-icon name="swap" />{{ __('Move to night shift') }}</button>
                </div>
            @endcan
            <div class="filters">
                {{-- Not for today: the machine is still filling it in, and people not in yet would be marked wrongly. --}}
                @if ($date->lt(today()))
                    <button type="button" class="btn sm" data-all="present"><x-icon name="check" />{{ __('Mark unmarked as present') }}</button>
                    <button type="button" class="btn sm" data-all="off">{{ __('Mark all off (holiday)') }}</button>
                @else
                    <span class="muted">{{ __('Today fills in from the attendance machine; only change what needs correcting.') }}</span>
                @endif
            </div>
        </div>
        <div class="tbl-wrap"><table>
            <thead><tr><th style="width:34px"><input type="checkbox" id="tickAll" aria-label="{{ __('Select all') }}"></th><th>{{ __('Employee') }}</th><th>{{ __('Status') }}</th><th>{{ __('In') }}</th><th>{{ __('Out') }}</th><th>{{ __('Late') }}</th><th>{{ __('Early') }}</th><th>{{ __('Worked') }}</th><th>{{ __('Overtime (hours)') }}</th><th>{{ __('All punches') }}</th><th>{{ __('Note') }}</th></tr></thead>
            <tbody>
            @foreach ($employees as $e)
                @php
                    $r = $records->get($e->id);
                    $old = fn ($f, $v = null) => old("rows.{$e->id}.{$f}", $v);
                @endphp
                @php $kind = $r?->shiftKind() ?? ($e->shift === 'night' ? 'night' : 'day'); @endphp
                <tr data-row data-shift="{{ $kind }}" data-status="{{ $r?->status->value ?? 'none' }}" data-late="{{ $r && $r->lateMinutes() > 0 ? 1 : 0 }}" data-ot="{{ $r && $r->overtime_minutes > 0 ? 1 : 0 }}">
                    <td><input type="checkbox" form="shiftForm" name="employee_ids[]" value="{{ $e->id }}" data-tick aria-label="{{ __('Select :name', ['name' => $e->name]) }}"></td>
                    <td><span class="att-dot {{ $r?->tone() }}" data-dot></span><b>{{ $e->localName() }}</b> <span @class(['shift-tag', 'night' => $kind === 'night'])>{{ $kind === 'night' ? __('Night') : __('Day') }}</span><div class="meta">{{ $e->code }} · {{ $e->designation }}@if ($r?->source === 'device') · {{ __('from device') }}@endif</div></td>
                    <td>
                        <select name="rows[{{ $e->id }}][status]" class="select att-status" aria-label="{{ __('Status for :name', ['name' => $e->name]) }}">
                            <option value="">{{ __('Not marked') }}</option>
                            @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected($old('status', $r?->status->value) === $s->value)>{{ $s->label() }}</option>@endforeach
                        </select>
                    </td>
                    <td><input class="select att-time" type="time" name="rows[{{ $e->id }}][check_in]" value="{{ $old('check_in', $r?->check_in ? substr($r->check_in, 0, 5) : null) }}" aria-label="{{ __('In') }}"></td>
                    <td><input class="select att-time" type="time" name="rows[{{ $e->id }}][check_out]" value="{{ $old('check_out', $r?->check_out ? substr($r->check_out, 0, 5) : null) }}" aria-label="{{ __('Out') }}"></td>
                    @php
                        $worked = $r && $r->workedMinutes() > 0 ? \App\Models\Attendance::hm($r->workedMinutes()) : null;
                        $dayPunches = $e->device_code !== null ? ($punches->get($e->device_code) ?? collect()) : collect();
                    @endphp
                    <td class="num att-late" style="white-space:nowrap" data-late>{{ $r ? \App\Models\Attendance::hm($r->lateMinutes()) : '—' }}</td>
                    <td class="num att-early" style="white-space:nowrap" data-early>{{ $r ? \App\Models\Attendance::hm($r->earlyMinutes()) : '—' }}</td>
                    <td class="num" style="white-space:nowrap" data-worked>{{ $worked ?? '—' }}</td>
                    <td><input class="select att-ot" type="number" step="any" min="0" max="48" name="rows[{{ $e->id }}][overtime_hours]" value="{{ $old('overtime_hours', $r && $r->overtime_minutes ? round($r->overtime_minutes / 60, 2) : null) }}" style="width:90px" aria-label="{{ __('Overtime (hours)') }}"></td>
                    <td style="min-width:120px">@forelse ($dayPunches as $p)<span class="who" style="margin:0 4px 2px 0">{{ $p->punched_at->format('g:i A') }}</span>@empty<span class="muted">—</span>@endforelse</td>
                    <td><input class="select" type="text" name="rows[{{ $e->id }}][note]" value="{{ $old('note', $r?->note) }}" style="min-width:140px" aria-label="{{ __('Note') }}"></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="form-actions"><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save attendance') }}</button></div>
    </form>

    @push('scripts')
        <script>
            (() => {
                // Each shift: [start, end, late grace, early grace, overtime grace] in minutes; the night shift ends the next morning.
                const shifts = @js($shifts), sunday = @js($date->isSunday()), stayOver = 240;
                // Within a grace it does not count; past it, the whole time does.
                const beyond = (m, grace) => m > grace ? m : 0;
                const mins = v => { if (!v) { return null; } const [h, m] = v.split(':').map(Number); return h * 60 + m; };
                const hm = m => m <= 0 ? '—' : (m >= 60 ? Math.floor(m / 60) + 'h ' + String(m % 60).padStart(2, '0') + 'm' : m + 'm');
                // In and out on the shift's clock: night times after midnight, and an Out not after the In, are the next day.
                const clock = (inV, outV, s) => {
                    let i = mins(inV), o = mins(outV);
                    if (i !== null && i < s[0] - 720) { i += 1440; }
                    if (o !== null) { const ref = i ?? s[0]; while (o <= ref) { o += 1440; } }
                    return [i, o];
                };
                document.querySelectorAll('[data-row]').forEach(row => {
                    const status = row.querySelector('.att-status'), inp = row.querySelector('[name$="[check_in]"]'), out = row.querySelector('[name$="[check_out]"]'), ot = row.querySelector('.att-ot');
                    const sync = () => { const present = status.value === 'present'; [inp, out, ot].forEach(el => el.disabled = !present); };
                    // Late after the shift start, early before its end, overtime after its end.
                    const s = shifts[row.dataset.shift] || shifts.day;
                    const calc = () => {
                        const [i, o] = clock(inp.value, out.value, s), present = status.value === 'present';
                        const late = present && !sunday && i !== null ? beyond(Math.max(0, i - s[0]), s[2]) : 0;
                        const early = present && !sunday && o !== null ? beyond(Math.max(0, s[1] - o), s[3]) : 0;
                        row.querySelector('[data-late]').textContent = hm(late);
                        row.querySelector('[data-early]').textContent = hm(early);
                        row.querySelector('[data-worked]').textContent = i !== null && o !== null ? hm(o - i) : '—';
                        if (o !== null) {
                            // Sunday: all of it is overtime. Staying until the next shift starts adds 4 hours for sleep.
                            const extra = o >= s[0] + 1440 ? stayOver : 0;
                            const otMin = sunday ? (i !== null ? o - i : 0) + extra : beyond(Math.max(0, o - s[1]), s[4]) + extra;
                            ot.value = Math.round(otMin / 60 * 100) / 100 || '';
                        }
                        const dot = row.querySelector('[data-dot]');
                        dot.className = 'att-dot ' + (status.value === 'absent' ? 'absent' : !present ? '' : late > 0 ? 'late' : +ot.value > 0 ? 'overtime' : 'present');
                    };
                    status.addEventListener('change', () => { sync(); calc(); }); inp.addEventListener('change', calc); out.addEventListener('change', calc); ot.addEventListener('input', calc);
                    sync();
                });
                // The cards at the top filter the rows: present, absent (incl. no punch), late, overtime.
                const rows = [...document.querySelectorAll('[data-row]')], cards = [...document.querySelectorAll('[data-filter]')];
                const shows = {
                    present: r => r.dataset.status === 'present',
                    absent: r => r.dataset.status === 'absent' || r.dataset.status === 'none',
                    late: r => r.dataset.late === '1',
                    overtime: r => r.dataset.ot === '1',
                };
                let active = null;
                const applyFilter = which => {
                    active = active === which ? null : which;
                    rows.forEach(r => { r.hidden = active !== null && !shows[active](r); });
                    cards.forEach(c => c.classList.toggle('on', c.dataset.filter === active));
                };
                cards.forEach(c => {
                    c.addEventListener('click', () => applyFilter(c.dataset.filter));
                    c.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); applyFilter(c.dataset.filter); } });
                });
                // Ticking people to move them to a shift.
                const ticks = [...document.querySelectorAll('[data-tick]')], tickAll = document.getElementById('tickAll'), tickedCount = document.getElementById('tickedCount');
                const showTicked = () => { const n = ticks.filter(t => t.checked).length; if (tickedCount) { tickedCount.textContent = n ? n + ' ' + @js(__('selected')) : ''; } };
                ticks.forEach(t => t.addEventListener('change', showTicked));
                tickAll?.addEventListener('change', () => { ticks.forEach(t => { if (!t.closest('tr').hidden) { t.checked = tickAll.checked; } }); showTicked(); });
                document.querySelectorAll('[data-all]').forEach(btn => btn.addEventListener('click', () => {
                    document.querySelectorAll('.att-status').forEach(s => {
                        if (btn.dataset.all === 'off' || !s.value) { s.value = btn.dataset.all; s.dispatchEvent(new Event('change')); }
                    });
                }));
            })();
        </script>
    @endpush
</x-layouts.app>
