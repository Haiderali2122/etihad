<x-layouts.app :title="__('New work order')">
    <x-page-head :back="route('work-orders.index')" :title="__('New work order :n', ['n' => $number])" :subtitle="__('Rims are worked out from the book\'s formats and checked against stock before you save.')" />

    @if ($books->isEmpty())
        <div class="card"><div class="empty"><b>{{ __('No books yet') }}</b><a href="{{ route('books.create') }}">{{ __('Add a book with its formats first.') }}</a></div></div>
    @else
        <form method="POST" action="{{ route('work-orders.store') }}" class="card" style="max-width:980px">
            @csrf
            <div class="card-b"><div class="form">
                <div @class(['field', 'full', 'has-err' => $errors->has('book_format_id')])>
                    <label for="f_book_format_id">{{ __('Book') }} <span class="muted">*</span></label>
                    <select id="f_book_format_id" name="book_format_id" required>
                        <option value="">{{ __('Choose book') }}</option>
                        @foreach ($books as $book)
                            <optgroup label="{{ $book->name }}">
                                @foreach ($book->formats as $f)
                                    <option value="{{ $f->id }}" data-forms="{{ $f->formsLabel() }}" data-paper="{{ $f->paper_type_id }}" @selected((string) old('book_format_id', $formatId) === (string) $f->id)>
                                        {{ $book->name }}{{ $book->formats->count() > 1 ? ' — '.$f->name : '' }} ({{ trans_choice(':count form|:count forms', (int) ceil((float) $f->forms), ['count' => $f->formsLabel()]) }})
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('book_format_id')<span class="err">{{ $message }}</span>@enderror
                </div>
                <x-field name="copies" type="number" min="1" :label="__('Copies')" required />
                <x-field name="wastage_percent" type="number" step="0.01" min="0" :label="__('Wastage %')" :value="$defaultWastage" required />

                <div @class(['field', 'full', 'has-err' => $errors->has('paper_type_id') || $errors->has('party_id')])>
                    <label for="f_stock">{{ __('Paper and party') }} <span class="muted">*</span></label>
                    <select id="f_stock" required>
                        <option value="">{{ __('Choose from stock') }}</option>
                        @foreach ($balances as $row)
                            <option value="{{ $row['paper_type']->id }}-{{ $row['party']->id }}" data-type="{{ $row['paper_type']->id }}" data-balance="{{ $row['balance'] }}" data-sheets="{{ $row['paper_type']->sheets_per_unit }}"
                                @selected(old('paper_type_id').'-'.old('party_id') === $row['paper_type']->id.'-'.$row['party']->id)>
                                {{ $row['paper_type']->code }} {{ $row['paper_type']->label() }} — {{ $row['party']->name }} ({{ number_format($row['balance']) }} {{ __($row['paper_type']->unit) }})
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="paper_type_id" id="f_paper_type_id" value="{{ old('paper_type_id') }}">
                    <input type="hidden" name="party_id" id="f_party_id" value="{{ old('party_id') }}">
                    @error('paper_type_id')<span class="err">{{ $message }}</span>@enderror
                </div>

                <div class="field full"><div class="calc">
                    <div class="lbl">{{ __('Rims needed') }}</div>
                    <div class="big num" id="rimsOut">—</div>
                    <div class="eq" id="rimsEq">{{ __('Formats × copies ÷ sheets per rim, plus wastage %') }}</div>
                    <div id="stockcheck"></div>
                </div></div>
                {{-- Short: the rest comes from another stock, and the order's party owes it (Paper stock → Paper owed). --}}
                <div @class(['field', 'full', 'has-err' => $errors->has('shortage_stock')]) id="shortageBox" hidden>
                    <div class="alert"><x-icon name="alert" /><div>
                        <b id="shortageText"></b>
                        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                            <label for="f_shortage_stock" style="margin:0">{{ __('Take the rest from') }}</label>
                            <select class="select" name="shortage_stock" id="f_shortage_stock" style="min-width:320px;max-width:100%"></select>
                        </div>
                        <div class="meta" style="margin-top:6px">{{ __('The order takes both, and the shortage is kept under Paper stock → Paper owed until it is paid back.') }}</div>
                    </div></div>
                    @error('shortage_stock')<span class="err">{{ $message }}</span>@enderror
                </div>

                <x-field name="mode" :label="__('Print where')" :options="collect(\App\Enums\PrintMode::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])" :value="old('mode', 'in_house')" required />
                <div id="inHouse"><x-field name="machine_id" :label="__('Machine')" :options="$machines->mapWithKeys(fn ($m) => [$m->id => $m->code.' '.$m->name])" :placeholder="__('Choose machine')" /></div>
                <div id="outside" hidden><x-field name="press_id" :label="__('Outside press')" :options="$presses->pluck('name', 'id')" :placeholder="__('Choose press')" :hint="$presses->isEmpty() ? __('Add the press under Parties & suppliers as an “Outside press”.') : null" />
                    <x-field name="printing_rate" type="number" step="0.01" min="0" :label="__('Printing rate per rim (Rs)')" :hint="__('Younus takes this to the press; it is added to his khata for the rims sent.')" /></div>
                <x-field name="buyer_id" :label="__('For buyer (optional)')" :options="$buyers->pluck('name', 'id')" :placeholder="__('Not set')" />
                <x-field name="ordered_on" type="date" :label="__('Order date')" :value="today()->toDateString()" required />
                <x-field name="due_on" type="date" :label="__('Due date')" />
                <x-field name="note" :label="__('Note')" full />
            </div></div>
            <div class="form-actions"><a class="btn" href="{{ route('work-orders.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Create order') }}</button></div>
        </form>
        @push('scripts')
            <script>
                (() => {
                    const $ = id => document.getElementById(id);
                    const book = $('f_book_format_id'), stock = $('f_stock'), copies = $('f_copies'), waste = $('f_wastage_percent'), mode = $('f_mode');
                    const fmt = n => Math.round(n).toLocaleString('en-PK');
                    // Any other stock can cover a shortage: the same paper first, then every other paper with rims.
                    const covers = @js($covers), box = $('shortageBox'), coverSelect = $('f_shortage_stock'), oldCover = @js(old('shortage_stock'));
                    const esc = t => String(t).replace(/[&<>"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
                    const shortage = short => {
                        const [type, party] = (stock.value || '-').split('-');
                        const others = covers.filter(c => !(String(c.type) === type && String(c.party) === party));
                        const same = others.filter(c => String(c.type) === type), other = others.filter(c => String(c.type) !== type);
                        box.hidden = short <= 0;
                        coverSelect.disabled = short <= 0;
                        if (short <= 0) { return; }
                        $('shortageText').textContent = @js(__('Short by')) + ' ' + fmt(short) + ' ' + @js(__('rims')) + '.' + (others.length ? '' : ' ' + @js(__('No other stock has rims.')));
                        const option = c => `<option value="${c.type}-${c.party}" ${c.balance < short ? 'disabled' : ''}>${esc(c.name)} · ${esc(c.paper)} — ${fmt(c.balance)} ${@js(__('rims'))}</option>`;
                        const keep = coverSelect.value || oldCover;
                        coverSelect.innerHTML = `<option value="">${@js(__('Pick the stock'))}</option>`
                            + (same.length ? `<optgroup label="${esc(@js(__('Same paper')))}">${same.map(option).join('')}</optgroup>` : '')
                            + (other.length ? `<optgroup label="${esc(@js(__('Other paper')))}">${other.map(option).join('')}</optgroup>` : '');
                        if (keep && others.some(c => `${c.type}-${c.party}` === String(keep))) { coverSelect.value = keep; }
                    };
                    const calc = () => {
                        const [type, party] = (stock.value || '-').split('-');
                        $('f_paper_type_id').value = type || ''; $('f_party_id').value = party || '';
                        const forms = +(book.selectedOptions[0]?.dataset.forms || 0), c = +copies.value || 0, w = +waste.value || 0;
                        const sheets = +(stock.selectedOptions[0]?.dataset.sheets || 500), have = +(stock.selectedOptions[0]?.dataset.balance || 0);
                        if (!forms || !c) { $('rimsOut').textContent = '—'; $('stockcheck').innerHTML = ''; shortage(0); return; }
                        const base = forms * c / sheets, need = Math.round(base * (1 + w / 100));
                        $('rimsOut').textContent = fmt(need) + ' {{ __('rims') }}';
                        $('rimsEq').textContent = `${forms} × ${fmt(c)} ÷ ${sheets} = ${fmt(base)} + ${w}% = ${fmt(need)}`;
                        $('stockcheck').innerHTML = !stock.value ? '' : need <= have
                            ? `<div class="stockcheck ok">${fmt(have)} {{ __('in stock') }} · ${fmt(have - need)} {{ __('left after this order') }}</div>`
                            : `<div class="stockcheck no">{{ __('Only') }} ${fmt(have)} {{ __('in stock') }}</div>`;
                        shortage(stock.value && need > have ? need - have : 0);
                    };
                    book.addEventListener('change', () => {
                        const paper = book.selectedOptions[0]?.dataset.paper;
                        if (paper && !stock.value) {
                            const best = [...stock.options].filter(o => o.dataset.type === paper).sort((a, b) => b.dataset.balance - a.dataset.balance)[0];
                            if (best) { stock.value = best.value; }
                        }
                        calc();
                    });
                    [stock, copies, waste].forEach(el => el.addEventListener('input', calc));
                    stock.addEventListener('change', calc);
                    const where = () => { const out = mode.value === 'outsource'; $('inHouse').hidden = out; $('outside').hidden = !out; };
                    mode.addEventListener('change', where);
                    where(); calc();
                })();
            </script>
        @endpush
    @endif
</x-layouts.app>
