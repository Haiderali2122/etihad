<x-layouts.app :title="__('Mark attendance')">
    <x-page-head :title="__('Attendance for :date', ['date' => $date->translatedFormat('l j F Y')])" :subtitle="__('Pick each person\'s status. Overtime counts only for people marked present.')">
        <x-slot:actions>
            <form method="GET" class="filters" style="align-items:center">
                <input class="select" type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" aria-label="{{ __('Date') }}" onchange="this.form.submit()">
            </form>
            <a class="btn" href="{{ route('attendance.index', ['month' => $date->format('Y-m')]) }}">{{ __('Month view') }}</a>
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('attendance.save-day') }}" class="card">
        @csrf @method('PUT')
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <div class="toolbar">
            <span class="muted">{{ __(':n staff · :m already marked', ['n' => $employees->count(), 'm' => $records->count()]) }}</span>
            <div class="filters">
                <button type="button" class="btn sm" data-all="present"><x-icon name="check" />{{ __('Mark unmarked as present') }}</button>
                <button type="button" class="btn sm" data-all="off">{{ __('Mark all off (holiday)') }}</button>
            </div>
        </div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Status') }}</th><th>{{ __('In') }}</th><th>{{ __('Out') }}</th><th>{{ __('Overtime (hours)') }}</th><th>{{ __('Note') }}</th></tr></thead>
            <tbody>
            @foreach ($employees as $e)
                @php
                    $r = $records->get($e->id);
                    $old = fn ($f, $v = null) => old("rows.{$e->id}.{$f}", $v);
                @endphp
                <tr data-row>
                    <td><b>{{ $e->localName() }}</b><div class="meta">{{ $e->code }} · {{ $e->designation }}@if ($r?->source === 'device') · {{ __('from device') }}@endif</div></td>
                    <td>
                        <select name="rows[{{ $e->id }}][status]" class="select att-status" aria-label="{{ __('Status for :name', ['name' => $e->name]) }}">
                            <option value="">{{ __('Not marked') }}</option>
                            @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected($old('status', $r?->status->value) === $s->value)>{{ $s->label() }}</option>@endforeach
                        </select>
                    </td>
                    <td><input class="select att-time" type="time" name="rows[{{ $e->id }}][check_in]" value="{{ $old('check_in', $r?->check_in ? substr($r->check_in, 0, 5) : null) }}" aria-label="{{ __('In') }}"></td>
                    <td><input class="select att-time" type="time" name="rows[{{ $e->id }}][check_out]" value="{{ $old('check_out', $r?->check_out ? substr($r->check_out, 0, 5) : null) }}" aria-label="{{ __('Out') }}"></td>
                    <td><input class="select att-ot" type="number" step="0.5" min="0" max="16" name="rows[{{ $e->id }}][overtime_hours]" value="{{ $old('overtime_hours', $r && $r->overtime_minutes ? round($r->overtime_minutes / 60, 2) : null) }}" style="width:90px" aria-label="{{ __('Overtime (hours)') }}"></td>
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
                const standard = {{ (float) $standardHours }};
                document.querySelectorAll('[data-row]').forEach(row => {
                    const status = row.querySelector('.att-status'), inp = row.querySelector('[name$="[check_in]"]'), out = row.querySelector('[name$="[check_out]"]'), ot = row.querySelector('.att-ot');
                    const sync = () => { const present = status.value === 'present'; [inp, out, ot].forEach(el => el.disabled = !present); };
                    // Work out overtime from in/out times: anything beyond the normal day.
                    const calc = () => {
                        if (!inp.value || !out.value) { return; }
                        const [h1, m1] = inp.value.split(':').map(Number), [h2, m2] = out.value.split(':').map(Number);
                        let mins = (h2 * 60 + m2) - (h1 * 60 + m1); if (mins < 0) { mins += 1440; }
                        ot.value = Math.max(0, Math.round((mins / 60 - standard) * 2) / 2) || '';
                    };
                    status.addEventListener('change', sync); inp.addEventListener('change', calc); out.addEventListener('change', calc);
                    sync();
                });
                document.querySelectorAll('[data-all]').forEach(btn => btn.addEventListener('click', () => {
                    document.querySelectorAll('.att-status').forEach(s => {
                        if (btn.dataset.all === 'off' || !s.value) { s.value = btn.dataset.all; s.dispatchEvent(new Event('change')); }
                    });
                }));
            })();
        </script>
    @endpush
</x-layouts.app>
