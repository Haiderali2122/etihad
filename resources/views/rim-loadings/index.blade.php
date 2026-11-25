@php
    $qty = fn ($v) => \App\Models\InventoryItem::qty($v);
    $into = \App\Enums\RimMove::IntoGodown->label();
    $orderOptions = $orders->map(fn ($o) => ['value' => $o->number.' · '.$o->bookName(), 'number' => $o->number, 'book' => $o->bookName(), 'rims' => (float) $o->rims_needed, 'printing_rate' => $o->printing_rate !== null ? (float) $o->printing_rate : null, 'stage' => $o->stage->value])->values();
    $paperOptions = $paperTypes->map(fn ($p) => ['id' => $p->id, 'label' => $p->label()])->values();
    $prevWeek = $week->copy()->subWeek()->toDateString();
    $nextWeek = $week->copy()->addWeek();
@endphp
<x-layouts.app :title="__('Younus ka khata')">
    <x-page-head :title="__('Younus ka khata')" :subtitle="__('All the work Younus does, written as it happened: rims into the godown, to and from the presses, to folding, truck loading, anything else. He gets advances during the week (Sunday to Saturday) and the rest on Saturday.')">
        <x-slot:actions>
            <a class="btn" href="{{ route('rim-loadings.receipt', ['week' => $week->toDateString(), 'kind' => 'work']) }}"><x-icon name="printer" />{{ __('Work receipt') }}</a>
            <a class="btn" href="{{ route('rim-loadings.receipt', ['week' => $week->toDateString(), 'kind' => 'advance']) }}"><x-icon name="printer" />{{ __('Advance receipt') }}</a>
            {{-- The week's receipt once he has been paid for it. --}}
            @if ($weekPaid)<a class="btn primary" href="{{ route('rim-loadings.receipt', ['week' => $week->toDateString(), 'kind' => 'week']) }}"><x-icon name="printer" />{{ __('Week receipt') }}</a>@endif
        </x-slot:actions>
    </x-page-head>
    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    {{-- The week and what is owed. --}}
    <div class="grid g4">
        <x-kpi icon="file" :label="__('This week\'s bill')" :foot="$week->format('D j M').' – '.$weekEnd->format('D j M')">Rs {{ number_format($weekBill) }}</x-kpi>
        <x-kpi icon="wallet" tone="warn" :label="__('Advances this week')">Rs {{ number_format($weekAdvances) }}</x-kpi>
        <x-kpi icon="alert" :tone="$toPay > 0 ? 'bad' : 'good'" :label="__('To pay Younus')" :foot="__('All bills less everything given')">Rs {{ number_format($toPay) }}</x-kpi>
        <div class="card kpi"><div class="kpi-top"><span class="kpi-label">{{ __('Week') }}</span></div>
            <div style="display:flex;gap:6px;align-items:center;margin-top:8px">
                <a class="btn sm" href="{{ route('rim-loadings.index', ['week' => $prevWeek]) }}">‹</a>
                <b>{{ $week->format('j M') }} – {{ $weekEnd->format('j M') }}</b>
                @if ($nextWeek->lte(today()))<a class="btn sm" href="{{ route('rim-loadings.index', ['week' => $nextWeek->toDateString()]) }}">›</a>@endif
            </div>
        </div>
    </div>

    @can('orders.manage')
        <div class="grid g-main">
            {{-- Add work: written freely; the site suggests the order and paper from what is typed. --}}
            @php
                // Entries to show: the ones typed before (if the save was refused), else one, filled from the order when opened from it.
                // The next empty one only appears once something is typed in the last.
                $entries = array_values(array_filter((array) old('rows', []), 'is_array')) ?: [[
                    'work_type' => $prefillKind?->label(), 'work_order' => $prefill ? $prefill->number.' · '.$prefill->bookName() : '',
                    'quantity' => $prefill ? (float) $prefill->rims_needed : '', 'unit' => 'rims',
                ]];
            @endphp
            <form method="POST" action="{{ route('rim-loadings.store-line') }}" class="card form-card" id="workForm">
                @csrf
                <div class="card-h"><div><h3>{{ __('Add work') }}</h3><div class="sub">{{ __('One entry per job; a new entry opens as you fill one, and Save stores them all. The rate fills in from last time for the same work.') }}</div></div>
                    <div style="display:flex;gap:8px;align-items:center"><label for="f_loaded_on" class="muted">{{ __('Date') }}</label><input class="select" type="date" id="f_loaded_on" name="loaded_on" value="{{ old('loaded_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
                </div>
                <div class="card-b" id="entries">
                    @foreach ($entries as $i => $e)
                        <div class="khata-entry" data-entry>
                            <div class="ke-line">
                                <span class="ke-no" data-no>{{ $loop->iteration }}</span>
                                <input class="select" type="text" name="rows[{{ $i }}][work_type]" value="{{ $e['work_type'] ?? '' }}" list="workTypes" maxlength="80" autocomplete="off" placeholder="{{ __('Work (optional)') }}" aria-label="{{ __('Work') }}" data-work style="width:210px">
                                <input class="select" type="text" name="rows[{{ $i }}][details]" value="{{ $e['details'] ?? '' }}" maxlength="1000" autocomplete="off" placeholder="{{ __('Details, e.g. 4C book 16 forme to folding') }}" aria-label="{{ __('Details') }}" data-details style="flex:1;min-width:180px">
                                <input class="select" type="text" name="rows[{{ $i }}][work_order]" value="{{ $e['work_order'] ?? '' }}" list="orderList" maxlength="120" autocomplete="off" placeholder="{{ __('Work order (optional)') }}" aria-label="{{ __('Work order') }}" data-order style="width:230px">
                            </div>
                            <div class="ke-line">
                                <span class="ke-no"></span>
                                <input class="select" type="number" step="0.01" min="0" name="rows[{{ $i }}][quantity]" value="{{ $e['quantity'] ?? '' }}" placeholder="{{ __('Rims / books') }}" aria-label="{{ __('Quantity') }}" data-qty style="width:120px;text-align:end">
                                <input class="select" type="text" name="rows[{{ $i }}][unit]" value="{{ $e['unit'] ?? 'rims' }}" list="unitList" maxlength="20" autocomplete="off" aria-label="{{ __('Unit') }}" data-unit style="width:100px">
                                <span class="muted">×</span>
                                <input class="select" type="number" step="0.01" min="0" name="rows[{{ $i }}][rate]" value="{{ $e['rate'] ?? '' }}" placeholder="{{ __('Rate') }}" aria-label="{{ __('Rate') }}" data-rate style="width:110px;text-align:end">
                                <span class="muted">=</span>
                                <b class="num" data-total style="min-width:90px">Rs 0</b>
                                {{-- Removing an entry: not on the first one. --}}
                                <button type="button" class="icon-btn danger" data-remove aria-label="{{ __('Remove') }}" @if ($loop->first) hidden @endif><x-icon name="x" /></button>
                                {{-- Rims into the godown: which paper, whose, and where it is unloaded. --}}
                                <span data-godown hidden style="display:inline-flex;gap:6px;flex-wrap:wrap">
                                    <select class="select" name="rows[{{ $i }}][paper_type_id]" aria-label="{{ __('Paper') }}" data-paper style="max-width:240px"><option value="">{{ __('Pick the paper') }}</option>@foreach ($paperTypes as $p)<option value="{{ $p->id }}" @selected(($e['paper_type_id'] ?? null) == $p->id)>{{ $p->label() }}</option>@endforeach</select>
                                    <input class="select" type="text" name="rows[{{ $i }}][party_name]" value="{{ $e['party_name'] ?? '' }}" list="partyList" maxlength="120" autocomplete="off" placeholder="{{ __('Whose paper') }}" aria-label="{{ __('Whose paper') }}" style="width:170px">
                                    <select class="select" name="rows[{{ $i }}][godown_id]" aria-label="{{ __('Unloaded at') }}"><option value="">{{ __('Unloaded at') }}</option>@foreach ($godowns as $g)<option value="{{ $g->id }}" @selected(($e['godown_id'] ?? null) == $g->id)>{{ $g->name }}</option>@endforeach</select>
                                </span>
                            </div>
                            <div class="meta ke-sug" data-sug></div>
                        </div>
                    @endforeach
                    <datalist id="workTypes">@foreach ($workTypes as $t)<option value="{{ $t }}">@endforeach</datalist>
                    <datalist id="orderList">@foreach ($orderOptions as $o)<option value="{{ $o['value'] }}">@endforeach</datalist>
                    <datalist id="unitList">@foreach ($units as $u)<option value="{{ $u }}">@endforeach</datalist>
                    <datalist id="partyList">@foreach ($parties as $n)<option value="{{ $n }}">@endforeach</datalist>
                </div>
                <div class="form-actions" style="justify-content:space-between"><b>{{ __('Total') }}: <span class="num" id="grandTotal">Rs 0</span></b><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save to khata') }}</button></div>
            </form>

            <div style="display:flex;flex-direction:column;gap:18px">
                {{-- Advances during the week, like the foreman's cash. --}}
                <form method="POST" action="{{ route('rim-loadings.advance') }}" class="card">
                    @csrf
                    <div class="card-h"><div><h3>{{ __('Give advance') }}</h3><div class="sub">{{ __('Comes off the manager\'s cash.') }}</div></div></div>
                    <div class="card-b"><div class="form" style="grid-template-columns:minmax(0,1fr)">
                        <x-field name="amount" type="number" min="1" step="1" :label="__('Amount (Rs)')" required />
                        <x-field name="paid_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                        <x-field name="note" :label="__('Note')" />
                    </div></div>
                    <div class="form-actions"><button class="btn" type="submit"><x-icon name="wallet" />{{ __('Give advance') }}</button></div>
                </form>
                {{-- Saturday: the rest of his bill. --}}
                @if ($toPay > 0)
                    <form method="POST" action="{{ route('rim-loadings.settle') }}" class="card" onsubmit="return confirm(@js(__('Pay Younus Rs :n?', ['n' => number_format($toPay)])))">
                        @csrf
                        <div class="card-h"><div><h3>{{ __('Pay the rest (Saturday)') }}</h3><div class="sub">{{ __('His bills less the advances already given.') }}</div></div></div>
                        <div class="card-b"><div class="form" style="grid-template-columns:minmax(0,1fr)">
                            <x-field name="amount" type="number" min="1" step="1" :max="$toPay" :label="__('Amount (Rs)')" :value="$toPay" required />
                            <x-field name="paid_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                        </div></div>
                        <div class="form-actions"><button class="btn good" type="submit"><x-icon name="check" />{{ __('Pay Rs :n', ['n' => number_format($toPay)]) }}</button></div>
                    </form>
                @endif
            </div>
        </div>
    @endcan

    {{-- The work week by week, newest first; a thick line and the week's totals separate the weeks. --}}
    <div class="card">
        <div class="card-h"><div><h3>{{ __('Work by week') }}</h3><div class="sub">{{ __('Up to 8 weeks ending :date. Older weeks: use ‹ above.', ['date' => $weekEnd->format('j M Y')]) }}</div></div></div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Work') }}</th><th>{{ __('Work order') }}</th><th class="r">{{ __('Quantity') }}</th><th class="r">{{ __('Rate') }}</th><th class="r">{{ __('Amount') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($weeks as $w)
                @php $open = $loop->first; $key = $w['start']->toDateString(); @endphp
                <tr class="week-split" data-week-toggle="{{ $key }}" aria-expanded="{{ $open ? 'true' : 'false' }}">
                    <td colspan="7">
                        <div style="display:flex;flex-wrap:wrap;gap:6px 16px;align-items:center;justify-content:space-between">
                            <b>{{ __('Week') }} {{ $w['start']->format('D j M') }} – {{ $w['end']->format('D j M Y') }}</b>
                            <span class="meta" style="display:flex;gap:14px;flex-wrap:wrap">
                                <span>{{ __('Bill') }} <b>Rs {{ number_format($w['bill']) }}</b></span>
                                <span>{{ __('Advances') }} <b>Rs {{ number_format($w['advances']) }}</b></span>
                                <span>{{ __('Paid') }} <b>Rs {{ number_format($w['paid']) }}</b></span>
                                <span>{{ __('Left') }} <b @style(['color:var(--bad)' => $w['bill'] - $w['advances'] - $w['paid'] > 0])>Rs {{ number_format($w['bill'] - $w['advances'] - $w['paid']) }}</b></span>
                                @if ($w['paid'] > 0)<a class="btn sm" href="{{ route('rim-loadings.receipt', ['week' => $w['start']->toDateString(), 'kind' => 'week']) }}"><x-icon name="printer" />{{ __('Week receipt') }}</a>@endif
                                {{-- Previous weeks start closed; this opens or closes the week's records. --}}
                                <button type="button" class="btn sm" data-week-button>{{ $open ? __('Hide records') : __('Show records') }} ({{ $w['lines']->count() }})</button>
                            </span>
                        </div>
                    </td>
                </tr>
                @forelse ($w['lines'] as $l)
                    <tr data-href="{{ route('records.show', ['khata-line', $l->id]) }}" data-week-row="{{ $key }}" @unless ($open) hidden @endunless>
                        <td>{{ $l->loaded_on->format('D j M') }}</td>
                        <td><b>{{ $l->workLabel() }}</b>@if ($l->details)<div class="meta">{{ $l->details }}</div>@endif
                            @if ($l->paperReceipt)<div class="meta">{{ __('Into stock: :paper (:party)', ['paper' => $l->paperReceipt->paperType?->label(), 'party' => $l->paperReceipt->party?->name]) }}</div>@endif</td>
                        <td>@if ($l->workOrder)<a class="main" href="{{ route('work-orders.show', $l->work_order_id) }}" style="color:inherit">{{ $l->workOrder->number }}</a><div class="meta">{{ $l->workOrder->bookName() }}</div>@else<span class="muted">—</span>@endif</td>
                        <td class="r num">{{ $qty($l->rims) }} {{ $l->unit }}</td>
                        <td class="r num">{{ $qty($l->rate_per_rim) }}</td>
                        <td class="r num"><b>Rs {{ number_format($l->amount) }}</b></td>
                        <td class="r" style="white-space:nowrap">@unless ($l->isPaid())@can('orders.manage')<a class="btn sm" href="{{ route('rim-loadings.edit', $l) }}"><x-icon name="edit" />{{ __('Edit') }}</a> <x-delete-button :action="route('rim-loadings.destroy', $l)" :label="''" small aria-label="{{ __('Delete') }}" />@endcan @endunless</td>
                    </tr>
                @empty
                    <tr data-week-row="{{ $key }}" @unless ($open) hidden @endunless><td colspan="7" class="muted">{{ __('No work this week') }}</td></tr>
                @endforelse
            @empty
                <tr><td colspan="7"><div class="empty"><b>{{ __('No work yet') }}</b>{{ __('Add it with the form above.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Given to Younus this week') }}</h3></div></div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('What') }}</th><th class="r">{{ __('Amount') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($payments as $p)
                <tr data-href="{{ route('records.show', ['younus-payment', $p->id]) }}">
                    <td>{{ $p->paid_on->format('D j M') }}</td>
                    <td>{{ $p->isAdvance() ? __('Advance') : __('Weekly payment') }}@if ($p->note)<div class="meta">{{ $p->note }}</div>@endif</td>
                    <td class="r num"><b>Rs {{ number_format($p->amount) }}</b></td>
                    <td class="r">@can('orders.manage')<x-delete-button :action="route('records.destroy', ['younus-payment', $p->id])" :label="''" small aria-label="{{ __('Delete') }}" />@endcan</td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty"><b>{{ __('Nothing given this week') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    @push('scripts')
        <script>
            (() => {
                // A week's separator opens or closes its records.
                const showLabel = @js(__('Show records')), hideLabel = @js(__('Hide records'));
                document.querySelectorAll('[data-week-toggle]').forEach(split => split.addEventListener('click', e => {
                    if (e.target.closest('a')) { return; }
                    const key = split.dataset.weekToggle, open = split.getAttribute('aria-expanded') !== 'true';
                    split.setAttribute('aria-expanded', open ? 'true' : 'false');
                    document.querySelectorAll(`[data-week-row="${key}"]`).forEach(r => { r.hidden = !open; });
                    const btn = split.querySelector('[data-week-button]');
                    btn.textContent = btn.textContent.replace(open ? showLabel : hideLabel, open ? hideLabel : showLabel);
                }));

                const box = document.getElementById('entries');
                if (!box) { return; }
                const orders = @js($orderOptions), papers = @js($paperOptions), rates = @js((object) $lastRates), into = @js($into), printing = @js(\App\Enums\RimMove::Printing->label());
                const fmt = n => 'Rs ' + Math.round(n).toLocaleString('en-PK');
                const words = t => t.toLowerCase().split(/[^a-z0-9.]+/).filter(w => w.length > 1);
                const entries = () => [...box.querySelectorAll('[data-entry]')];
                const filled = e => ['[data-work]', '[data-details]', '[data-order]', '[data-qty]', '[data-rate]'].some(sel => e.querySelector(sel).value.trim() !== '');
                const complete = e => +e.querySelector('[data-qty]').value > 0 && e.querySelector('[data-rate]').value.trim() !== ''
                    && (e.querySelector('[data-work]').value !== into || [...e.querySelectorAll('[data-godown] select, [data-godown] input')].every(el => el.value.trim() !== ''));
                const grand = () => { document.getElementById('grandTotal').textContent = fmt(entries().reduce((t, e) => t + (+e.querySelector('[data-qty]').value || 0) * (+e.querySelector('[data-rate]').value || 0), 0)); };

                const wire = e => {
                    const work = e.querySelector('[data-work]'), details = e.querySelector('[data-details]'), order = e.querySelector('[data-order]');
                    const qty = e.querySelector('[data-qty]'), unit = e.querySelector('[data-unit]'), rate = e.querySelector('[data-rate]');
                    let autoRate = rate.value === '';
                    const total = () => { e.querySelector('[data-total]').textContent = fmt((+qty.value || 0) * (+rate.value || 0)); grand(); };
                    // The rate from last time for this work and unit (or the order's printing rate); it can be changed.
                    const fillRate = () => {
                        if (!autoRate) { return; }
                        const picked = orders.find(o => o.value === order.value);
                        const r = work.value === printing && picked?.printing_rate != null ? picked.printing_rate : rates[((work.value || @js(__('Other work'))) + '|' + unit.value).trim().toLowerCase()];
                        if (r !== undefined) { rate.value = r; total(); }
                    };
                    const showGodown = () => { e.querySelector('[data-godown]').hidden = work.value !== into; };
                    // Suggestions from what is typed: the work order (WO-number or book name) and the paper.
                    const suggest = () => {
                        const text = details.value, sug = e.querySelector('[data-sug]'), found = [];
                        const wo = text.match(/WO-?\s?(\d+)/i);
                        let o = wo ? orders.find(x => x.number.replace('-', '') === 'WO' + wo[1]) : null;
                        if (!o && text.trim().length > 2) { const t = text.toLowerCase(); o = orders.find(x => x.book && t.includes(x.book.toLowerCase())); }
                        if (o && order.value !== o.value) { found.push(['order', o.value, @js(__('Order')) + ': ' + o.value]); }
                        if (work.value === into) {
                            const tw = new Set(words(text)); let best = null, score = 1;
                            papers.forEach(p => { const sc = words(p.label).filter(w => tw.has(w)).length; if (sc > score) { best = p; score = sc; } });
                            if (best && String(e.querySelector('[data-paper]').value) !== String(best.id)) { found.push(['paper', best.id, @js(__('Paper')) + ': ' + best.label]); }
                        }
                        sug.innerHTML = found.length ? @js(__('Suggested:')) + ' ' + found.map((f, i) => `<button type="button" class="btn sm" data-use="${i}" style="margin:2px 4px 2px 0">${String(f[2]).replace(/[<>&"]/g, '')} — ${@js(__('Use'))}</button>`).join('') : '';
                        sug.querySelectorAll('[data-use]').forEach(b => b.addEventListener('click', () => {
                            const [kind, value] = found[+b.dataset.use];
                            if (kind === 'order') { order.value = value; order.dispatchEvent(new Event('change')); }
                            if (kind === 'paper') { e.querySelector('[data-paper]').value = value; }
                            suggest();
                        }));
                    };
                    order.addEventListener('change', () => { const o = orders.find(x => x.value === order.value); if (o && !qty.value) { qty.value = o.rims; } fillRate(); total(); });
                    [work, unit].forEach(el => el.addEventListener('input', () => { showGodown(); fillRate(); suggest(); }));
                    rate.addEventListener('input', () => { autoRate = false; total(); });
                    details.addEventListener('input', suggest);
                    qty.addEventListener('input', total);
                    e.querySelector('[data-remove]').addEventListener('click', () => {
                        if (entries().length > 1) { e.remove(); }
                        // The first entry never shows the cross.
                        entries().forEach((x, i) => { x.querySelector('[data-remove]').hidden = i === 0; });
                        renumber(); grand();
                    });
                    showGodown(); fillRate(); suggest(); total();
                };

                let next = entries().length;
                const renumber = () => entries().forEach((e, i) => { e.querySelector('[data-no]').textContent = i + 1; });
                // Once the last entry has something in it, a fresh empty one opens below.
                const ensureBlank = () => {
                    const last = entries().at(-1);
                    if (!last || !complete(last)) { return; }
                    const fresh = last.cloneNode(true);
                    fresh.querySelectorAll('input, select').forEach(el => {
                        el.name = el.name.replace(/rows\[\d+]/, `rows[${next}]`);
                        el.value = el.matches('[data-unit]') ? 'rims' : '';
                    });
                    fresh.querySelector('[data-sug]').innerHTML = '';
                    fresh.querySelector('[data-remove]').hidden = false;
                    next++;
                    box.insertBefore(fresh, box.querySelector('datalist'));
                    wire(fresh); renumber();
                };
                entries().forEach(wire);
                ensureBlank(); // an entry already filled (opened from an order, or brought back after a refused save) gets an empty one after it
                box.addEventListener('input', ensureBlank);
                box.addEventListener('change', ensureBlank);
                // Empty entries are not sent.
                document.getElementById('workForm').addEventListener('submit', () => {
                    entries().forEach(e => { if (!filled(e)) { e.querySelectorAll('input, select').forEach(el => el.disabled = true); } });
                });
                grand();
            })();
        </script>
    @endpush
</x-layouts.app>
