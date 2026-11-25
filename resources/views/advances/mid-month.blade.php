@php
    $foremen = $employees->where('is_foreman', true);
    $fmt = fn ($n) => number_format((int) $n);
@endphp
<x-layouts.app :title="__('Advance salary')">
    <x-page-head :title="__('Advance salary — :month', ['month' => $month->format('F Y')])" :subtitle="__('Paid on the 22nd. Advance salary starts at :p% of salary; change it if needed. Type what each person already took from the foreman; the rest is handed over now. The remaining salary and overtime are paid on the 10th of next month.', ['p' => \App\Models\InventoryItem::qty($percent)])">
        <x-slot:actions>
            <a class="btn" href="{{ route('salary-advances.mid-month', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}"><x-icon name="back" />{{ $month->copy()->subMonth()->format('M Y') }}</a>
            <a class="btn" href="{{ route('salary-advances.mid-month', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">{{ $month->copy()->addMonth()->format('M Y') }}</a>
            <a class="btn" href="{{ route('salary-advances.index') }}">{{ __('Back') }}</a>
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif
    @if ($locked)<div class="alert"><x-icon name="alert" /><div>{{ __('This month\'s salaries are finalized, so these amounts are locked. Reopen the payroll to change them.') }}</div></div>@endif

    <form method="POST" action="{{ route('salary-advances.mid-month.store') }}" class="card" id="asForm">
        @csrf
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <div class="toolbar">
            <div class="field" style="flex-direction:row;align-items:center;gap:10px"><label for="given_on">{{ __('Paid on') }}</label><input class="select" id="given_on" type="date" name="given_on" value="{{ old('given_on', $paidOn->toDateString()) }}" required @disabled($locked)></div>
            <div><span class="muted">{{ __('To hand over now') }}:</span> <b class="num" id="giveTotal">Rs 0</b></div>
        </div>

        @if ($foremen->isNotEmpty())
            <div class="card-b" style="display:flex;flex-wrap:wrap;gap:16px">
                @foreach ($foremen as $f)
                    <div class="field" style="min-width:220px">
                        <label>{{ __('Cash given to :name (10th–21st)', ['name' => $f->name]) }}</label>
                        <input type="hidden" value="{{ $cash[$f->id] ?? 0 }}" data-cash="{{ $f->id }}">
                        <div class="calc" style="padding:10px 14px"><b class="num">Rs {{ number_format($cash[$f->id] ?? 0) }}</b> <a class="meta" href="{{ route('foreman-cash.index', ['month' => $month->format('Y-m')]) }}">{{ __('Foreman cash') }} →</a>
                            <div class="meta" style="margin-top:4px">{{ __('Left with him after his team\'s kharcha') }}: <b class="num" data-left="{{ $f->id }}">Rs {{ number_format(($cash[$f->id] ?? 0) - $employees->where('foreman_id', $f->id)->where('is_foreman', false)->sum('taken')) }}</b></div></div>
                        @error("cash.{$f->id}")<span class="err">{{ $message }}</span>@enderror
                    </div>
                @endforeach
            </div>
        @endif

        <div class="tbl-wrap"><table class="pay-grid">
            <thead><tr><th style="width:40px">#</th><th>{{ __('Employee') }}</th><th class="r">{{ __('Salary') }}</th><th class="r">{{ __('Advance salary') }}</th><th class="r">{{ __('Already taken') }}</th><th class="r">{{ __('Give now') }}</th><th></th></tr></thead>
            <tbody id="asRows">
            @forelse ($employees as $e)
                <tr data-row data-emp="{{ $e->id }}" data-salary="{{ $e->monthly_salary }}" @if ($e->is_foreman) data-foreman="{{ $e->id }}" @elseif ($e->foreman_id) data-team="{{ $e->foreman_id }}" @endif>
                    <td class="num">{{ $loop->iteration }}</td>
                    <td><b>{{ $e->localName() }}</b><div class="meta">{{ $e->code }} · {{ $e->is_foreman ? __('Foreman') : ($e->foreman ? __('Under :name', ['name' => $e->foreman->name]) : $e->designation) }}</div></td>
                    <td class="r num">Rs {{ $fmt($e->monthly_salary) }}</td>
                    <td class="r">
                        <input class="select pay-in" type="number" min="0" max="{{ $e->monthly_salary }}" step="10" name="as[{{ $e->id }}]" value="{{ old("as.{$e->id}", $e->as_amount) }}" data-as aria-label="{{ __('Advance salary for :name', ['name' => $e->name]) }}" @disabled($locked)>
                        @error("as.{$e->id}")<div class="err">{{ $message }}</div>@enderror
                    </td>
                    <td class="r">
                        @if ($e->is_foreman)
                            <span class="num" data-kept>0</span><div class="meta">{{ __('kept of the cash') }}</div>
                        @else
                            <input class="select pay-in" type="number" min="0" step="10" name="taken[{{ $e->id }}]" value="{{ old("taken.{$e->id}", $e->taken ?: '') }}" data-taken aria-label="{{ __('Already taken by :name', ['name' => $e->name]) }}" @disabled($locked)>
                        @endif
                    </td>
                    <td class="r num"><b data-give>Rs 0</b><div class="meta" data-saved aria-live="polite"></div></td>
                    {{-- Saves only this person (even before the 22nd) and opens his slip to hand over. --}}
                    <td class="r">@unless ($locked)<button class="btn sm" type="submit" name="only" value="{{ $e->id }}"><x-icon name="printer" />{{ __('Save & slip') }}</button>@else @if ($e->advance_record)<a class="btn sm" href="{{ route('salary-advances.mid-month.slip', ['month' => $month->format('Y-m'), 'employee' => $e->id]) }}"><x-icon name="printer" />{{ __('Slip') }}</a>@endif @endunless</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><b>{{ __('No active staff') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        @unless ($locked)<div class="form-actions"><a class="btn" href="{{ route('salary-advances.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save advance salaries') }}</button></div>@endunless
    </form>
    @push('scripts')
        <script>
            (() => {
                const rows = [...document.querySelectorAll('[data-row]')], fmt = n => 'Rs ' + Math.round(n).toLocaleString('en-PK');
                const val = el => el ? (+el.value || 0) : 0;
                const refresh = () => {
                    // A foreman's "already taken" is the cash he was given less what his team took.
                    const kept = {};
                    document.querySelectorAll('[data-cash]').forEach(c => { kept[c.dataset.cash] = val(c); });
                    rows.filter(r => r.dataset.team).forEach(r => { if (r.dataset.team in kept) { kept[r.dataset.team] -= val(r.querySelector('[data-taken]')); } });
                    document.querySelectorAll('[data-left]').forEach(el => { el.textContent = fmt(kept[el.dataset.left] || 0); el.style.color = (kept[el.dataset.left] || 0) < 0 ? 'var(--bad, #c0392b)' : ''; });
                    let total = 0;
                    rows.forEach(r => {
                        const as = val(r.querySelector('[data-as]'));
                        let took = val(r.querySelector('[data-taken]'));
                        if (r.dataset.foreman) {
                            took = Math.max(0, kept[r.dataset.foreman] || 0);
                            r.querySelector('[data-kept]').textContent = (took).toLocaleString('en-PK');
                        }
                        const give = as - took;
                        const cell = r.querySelector('[data-give]');
                        cell.textContent = fmt(give);
                        cell.style.color = give < 0 ? 'var(--bad)' : '';
                        total += Math.max(0, give);
                    });
                    document.getElementById('giveTotal').textContent = fmt(total);
                };
                const form = document.getElementById('asForm');
                form.addEventListener('input', refresh);
                refresh();

                // Each row saves itself once an amount is typed (on leaving the box, or after a short pause), so
                // nothing is lost and no button is needed. The Save & slip button still opens the slip.
                const timers = new Map();
                const save = async row => {
                    const id = row.dataset.emp, note = row.querySelector('[data-saved]');
                    const body = new FormData();
                    body.append('_token', form.querySelector('[name=_token]').value);
                    body.append('month', form.querySelector('[name=month]').value);
                    body.append('given_on', document.getElementById('given_on').value);
                    body.append('only', id);
                    body.append(`as[${id}]`, row.querySelector('[data-as]').value || 0);
                    const taken = row.querySelector('[data-taken]');
                    if (taken) { body.append(`taken[${id}]`, taken.value || 0); }
                    note.style.color = ''; note.textContent = @js(__('Saving…'));
                    try {
                        const res = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const json = await res.json().catch(() => ({}));
                        if (res.ok) { note.style.color = 'var(--good, #2f855a)'; note.textContent = '✓ ' + @js(__('Saved')); }
                        else { note.style.color = 'var(--bad)'; note.textContent = json.message || @js(__('Not saved')); }
                    } catch (e) { note.style.color = 'var(--bad)'; note.textContent = @js(__('Not saved — check the internet')); }
                };
                const queue = (row, wait) => { clearTimeout(timers.get(row)); timers.set(row, setTimeout(() => save(row), wait)); };
                rows.forEach(row => row.querySelectorAll('[data-as], [data-taken]').forEach(input => {
                    if (input.disabled) { return; }
                    input.addEventListener('input', () => queue(row, 1500));
                    input.addEventListener('change', () => queue(row, 0));
                    // Enter saves this row instead of sending the whole sheet.
                    input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); queue(row, 0); } });
                }));
            })();
        </script>
    @endpush
</x-layouts.app>
