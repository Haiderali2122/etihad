<x-layouts.app :title="__('Issue paper')">
    <x-page-head :title="__('Issue paper')" :subtitle="__('Paper leaves the godown three ways: to an in-house machine, to an outside press, or back to the supplier. Each is deducted from that party\'s balance.')" />

    <form method="POST" action="{{ route('paper-issues.store') }}" class="card form-card" id="issueForm">
        @csrf
        <div class="card-b"><div class="form">
            <x-field name="kind" :label="__('Where is it going?')" :options="collect($kinds)->mapWithKeys(fn ($k) => [$k->value => $k->label()])" :value="$kind" required />
            <x-field name="issued_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />

            <div @class(['field', 'full', 'has-err' => $errors->has('paper_type_id') || $errors->has('party_id')])>
                <label for="f_stock">{{ __('Paper and party') }} <span class="muted">*</span></label>
                <select id="f_stock" required>
                    <option value="">{{ __('Choose from stock') }}</option>
                    @foreach ($balances as $row)
                        <option value="{{ $row['paper_type']->id }}-{{ $row['party']->id }}" data-type="{{ $row['paper_type']->id }}" data-party-name="{{ $row['party']->name }}" data-balance="{{ $row['balance'] }}" data-sheets="{{ $row['paper_type']->sheets_per_unit }}"
                            @selected(old('paper_type_id').'-'.old('party_id') === $row['paper_type']->id.'-'.$row['party']->id)>
                            {{ $row['paper_type']->code }} {{ $row['paper_type']->label() }} — {{ $row['party']->name }} ({{ number_format($row['balance']) }} {{ __($row['paper_type']->unit) }})
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="paper_type_id" id="f_paper_type_id" value="{{ old('paper_type_id') }}">
                <input type="hidden" name="party_id" id="f_party_id" value="{{ old('party_id') }}">
                @error('paper_type_id')<span class="err">{{ $message }}</span>@enderror
            </div>

            <x-field name="issued_to" :label="__('Sent to')" required list="issueTargets" :hint="__('Machine, outside press, or supplier name')" />
            <x-field name="reference" :label="__('Work order / reference')" placeholder="WO-1042" />

            <div class="field full" id="bookSection"><div class="calc">
                <div class="lbl">{{ __('Work out rims from the book') }}</div>
                <div @class(['field', 'has-err' => $errors->has('book_format_id')])>
                    <label for="f_book_format_id">{{ __('Book') }}</label>
                    <select id="f_book_format_id" name="book_format_id">
                        <option value="">{{ __('Not for a book') }}</option>
                        @foreach ($books as $book)
                            @if ($book->formats->count() === 1)
                                @php $f = $book->formats->first(); @endphp
                                <option value="{{ $f->id }}" data-forms="{{ $f->formsLabel() }}" data-paper="{{ $f->paper_type_id }}" @selected(old('book_format_id') == $f->id)>
                                    {{ $book->name }} — {{ trans_choice(':count form|:count forms', (int) ceil((float) $f->forms), ['count' => $f->formsLabel()]) }}
                                </option>
                            @else
                                <optgroup label="{{ $book->name }}">
                                    @foreach ($book->formats as $f)
                                        <option value="{{ $f->id }}" data-forms="{{ $f->formsLabel() }}" data-paper="{{ $f->paper_type_id }}" @selected(old('book_format_id') == $f->id)>
                                            {{ $book->name }} — {{ $f->name }}, {{ trans_choice(':count form|:count forms', (int) ceil((float) $f->forms), ['count' => $f->formsLabel()]) }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    @error('book_format_id')<span class="err">{{ $message }}</span>@enderror
                    @can('books.manage')<span class="hint"><a href="{{ route('books.create') }}">{{ __('Book not listed? Add it') }}</a></span>@endcan
                </div>
                <div class="form" style="grid-template-columns:repeat(3,minmax(0,1fr))">
                    <div class="field"><label for="c_formats">{{ __('Formats per book') }}</label><input id="c_formats" type="number" min="0" step="0.5"></div>
                    <x-field name="copies" type="number" min="1" :label="__('Copies')" />
                    <x-field name="wastage_percent" type="number" step="0.5" min="0" :label="__('Wastage %')" :value="$defaultWastage" />
                </div>
                <div class="eq" id="c_eq">{{ __('Formats × copies ÷ sheets per rim, plus wastage %') }}</div>
            </div></div>

            <x-field name="quantity" type="number" min="1" :label="__('Rims out')" required />
            <div class="field"><label>&nbsp;</label><div id="stockcheck"></div></div>
            <div class="field full" id="returnHint" hidden><div class="alert" style="background:var(--info-bg);color:var(--indigo)"><x-icon name="back" /><div>{{ __('Returns go back to the supplier as they are: enter only the quantity going out. No book or wastage is needed.') }}</div></div></div>
            <x-field name="note" :label="__('Note')" full />
        </div></div>
        <div class="form-actions">
            <a class="btn" href="{{ route('paper.index', ['tab' => 'issues']) }}">{{ __('Cancel') }}</a>
            <button class="btn primary" type="submit"><x-icon name="send" />{{ __('Issue paper') }}</button>
        </div>
    </form>

    <datalist id="issueTargets">@foreach ($machines as $m)<option value="{{ __('Machine') }} {{ $m->code }}">@endforeach</datalist>

    @push('scripts')
        <script>
            (() => {
                const $ = id => document.getElementById(id);
                const stock = $('f_stock'), qty = $('f_quantity'), check = $('stockcheck');
                const fmt = n => Math.round(n).toLocaleString('en-PK');
                const sync = () => {
                    const [type, party] = (stock.value || '-').split('-');
                    $('f_paper_type_id').value = type || '';
                    $('f_party_id').value = party || '';
                    const opt = stock.selectedOptions[0], have = +(opt?.dataset.balance || 0), need = +qty.value || 0;
                    if (!stock.value || !need) { check.innerHTML = ''; return; }
                    check.innerHTML = need <= have
                        ? `<div class="stockcheck ok">${fmt(have - need)} {{ __('left after this issue') }}</div>`
                        : `<div class="stockcheck no">{{ __('Only') }} ${fmt(have)} {{ __('in stock') }}</div>`;
                };
                const book = $('f_book_format_id');
                const calc = () => {
                    const f = +$('c_formats').value, c = +$('f_copies').value, w = +$('f_wastage_percent').value || 0;
                    const sheets = +(stock.selectedOptions[0]?.dataset.sheets || 500);
                    if (!f || !c) { $('c_eq').textContent = @json(__('Formats × copies ÷ sheets per rim, plus wastage %')); return; }
                    const base = f * c / sheets, need = Math.round(base * (1 + w / 100));
                    $('c_eq').textContent = `${f} × ${fmt(c)} ÷ ${sheets} = ${fmt(base)} + ${w}% = ${fmt(need)}`;
                    qty.value = need;
                    sync();
                };
                // Picking a book fills its forms, and its default paper if nothing is chosen yet.
                const pickBook = () => {
                    const opt = book.selectedOptions[0];
                    if (!opt?.value) { return; }
                    $('c_formats').value = opt.dataset.forms;
                    const paper = opt.dataset.paper;
                    if (paper && !stock.value) {
                        const matches = [...stock.options].filter(o => o.dataset.type === paper);
                        const best = matches.sort((a, b) => b.dataset.balance - a.dataset.balance)[0];
                        if (best) { stock.value = best.value; }
                    }
                    sync();
                    calc();
                    if (!$('f_copies').value) { $('f_copies').focus(); }
                };
                book.addEventListener('change', pickBook);

                // Returns to the supplier need only the quantity: hide and switch off the book and wastage fields.
                const kind = $('f_kind'), section = $('bookSection'), sentTo = $('f_issued_to');
                const sentToLabel = document.querySelector('label[for="f_issued_to"]');
                const labels = { normal: @json(__('Sent to')), back: @json(__('Returned to')) };
                let autoFilled = '';
                const applyKind = () => {
                    const isReturn = kind.value === 'return';
                    section.hidden = isReturn;
                    $('returnHint').hidden = !isReturn;
                    section.querySelectorAll('input, select').forEach(el => el.disabled = isReturn);
                    sentToLabel.firstChild.textContent = (isReturn ? labels.back : labels.normal) + ' ';
                    const partyName = stock.selectedOptions[0]?.dataset.partyName || '';
                    if (isReturn && partyName && (!sentTo.value || sentTo.value === autoFilled)) {
                        sentTo.value = autoFilled = partyName;
                    } else if (!isReturn && sentTo.value && sentTo.value === autoFilled) {
                        sentTo.value = autoFilled = '';
                    }
                    if (!isReturn) { calc(); }
                };
                kind.addEventListener('change', applyKind);
                stock.addEventListener('change', applyKind);
                stock.addEventListener('change', () => { sync(); calc(); });
                qty.addEventListener('input', sync);
                ['c_formats', 'f_copies', 'f_wastage_percent'].forEach(id => $(id).addEventListener('input', calc));
                if (book.value && !$('c_formats').value) { $('c_formats').value = book.selectedOptions[0].dataset.forms; }
                sync();
                calc();
                applyKind();
            })();
        </script>
    @endpush
</x-layouts.app>
