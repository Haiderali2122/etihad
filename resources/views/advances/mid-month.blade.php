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
                        <label for="cash_{{ $f->id }}">{{ __('Cash given to :name (11th–21st)', ['name' => $f->name]) }}</label>
                        <input class="select" type="number" min="0" step="10" id="cash_{{ $f->id }}" name="cash[{{ $f->id }}]" value="{{ old("cash.{$f->id}", $cash->get($f->id)?->amount) }}" data-cash="{{ $f->id }}" style="text-align:end" @disabled($locked)>
                        @error("cash.{$f->id}")<span class="err">{{ $message }}</span>@enderror
                    </div>
                @endforeach
            </div>
        @endif

        <div class="tbl-wrap"><table class="pay-grid">
            <thead><tr><th style="width:40px">#</th><th>{{ __('Employee') }}</th><th class="r">{{ __('Salary') }}</th><th class="r">{{ __('Advance salary') }}</th><th class="r">{{ __('Already taken') }}</th><th class="r">{{ __('Give now') }}</th></tr></thead>
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
                    <td class="r num"><b data-give>Rs 0</b></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><b>{{ __('No active staff') }}</b></div></td></tr>
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
                document.getElementById('asForm').addEventListener('input', refresh);
                refresh();
            })();
        </script>
    @endpush
</x-layouts.app>
