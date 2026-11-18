@php
    $editing = $bill->exists;
    $heading = $editing ? __('Edit :name bill :no', ['name' => $category->localName(), 'no' => $bill->receiptNumber()]) : __(':name bill', ['name' => $category->localName()]);
    $rows = old('items', $editing
        ? $bill->items->map(fn ($i) => ['item' => $i->description, 'quantity' => rtrim(rtrim((string) $i->quantity, '0'), '.'), 'rate' => rtrim(rtrim((string) $i->rate, '0'), '.')])->all()
        : []);
    // Always one empty row at the end; typing in it adds the next one.
    $rows = array_values(array_filter($rows, fn ($r) => collect(['item', 'quantity', 'rate'])->contains(fn ($f) => filled($r[$f] ?? null))));
    $rows[] = ['item' => '', 'quantity' => '', 'rate' => ''];
    $material = true;
    $cols = 6;
@endphp
<x-layouts.app :title="$heading">
    <x-page-head :back="$back" :title="$heading" :subtitle="__('One row per item: its quantity and price per quantity; the total is worked out. The bill is paid later, in parts or all at once; only what is paid comes off the manager\'s cash.')" />
    @error('total')<div class="flash err" role="alert"><x-icon name="alert" />{{ $message }}</div>@enderror
    <form method="POST" action="{{ $editing ? route('material-bills.update', $bill) : route('material-bills.store') }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="inventory_category_id" value="{{ $category->id }}">
        <div class="card-b"><div class="form">
            <x-field name="spent_on" type="date" :label="__('Date')" :value="$bill->bill_on?->toDateString() ?? today()->toDateString()" required />
                {{-- Type the supplier or pick one; a new name is added to Parties as a supplier, details can follow later. --}}
                <div @class(['field', 'has-err' => $errors->has('supplier')])>
                    <label for="f_supplier">{{ __('Supplier') }} @if ($category->addsStock())<span class="muted">*</span>@endif</label>
                    <input type="text" id="f_supplier" name="supplier" value="{{ old('supplier', $bill->supplier) }}" list="supplierList" autocomplete="off" maxlength="120" @required($category->addsStock())>
                    <datalist id="supplierList">@foreach ($suppliers as $s)<option value="{{ $s }}">@endforeach</datalist>
                    @error('supplier')<span class="err">{{ $message }}</span>@enderror
                </div>

            <div @class(['field', 'full', 'has-err' => $errors->has('items') || $errors->has('items.*')])>
                <div class="tbl-wrap"><table class="fmt-table no-stack">
                    <thead><tr>
                        <th style="width:44px">#</th>
                        <th style="min-width:200px">{{ __('Item') }}</th>
                        @if ($material)
                            <th class="r" style="width:120px">{{ __('Quantity') }}</th>
                            <th class="r" style="width:140px">{{ __('Price per quantity') }}</th>
                            <th class="r" style="width:140px">{{ __('Total') }}</th>
                        @else
                            <th class="r" style="width:160px">{{ __('Price') }}</th>
                        @endif
                        <th style="width:44px"></th>
                    </tr></thead>
                    <tbody id="itemRows">
                    @foreach ($rows as $i => $row)
                        <tr>
                            <td class="num" data-no>{{ $loop->iteration }}</td>
                            <td><input type="text" name="items[{{ $i }}][item]" value="{{ $row['item'] ?? '' }}" list="itemList" maxlength="120" autocomplete="off" aria-label="{{ __('Item') }}" data-item></td>
                            @if ($material)
                                <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}" aria-label="{{ __('Quantity') }}" style="text-align:end" data-qty></td>
                                <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][rate]" value="{{ $row['rate'] ?? '' }}" aria-label="{{ __('Price per quantity') }}" style="text-align:end" data-rate></td>
                                <td class="r num" data-line>Rs 0</td>
                            @else
                                <td><input type="number" min="1" name="items[{{ $i }}][amount]" value="{{ $row['amount'] ?? '' }}" aria-label="{{ __('Price') }}" style="text-align:end" data-amount></td>
                            @endif
                            <td><button type="button" class="icon-btn danger" data-remove aria-label="{{ __('Remove item') }}"><x-icon name="x" /></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="{{ $cols - 2 }}" style="font-weight:700">{{ __('Total') }}</td><td class="r num"><b id="itemsTotal">Rs 0</b></td><td></td></tr></tfoot>
                </table></div>
                <datalist id="itemList">@foreach ($suggestions as $s)<option value="{{ $s }}">@endforeach</datalist>
                @error('items')<span class="err">{{ __('Add at least one item with its price.') }}</span>@enderror
                @foreach ($errors->get('items.*') as $messages)<span class="err">{{ $messages[0] }}</span>@endforeach
            </div>

            @unless ($editing)
                <x-field name="paid_now" type="number" min="0" step="1" :label="__('Paid now (Rs)')" :hint="__('Leave empty if nothing is paid yet; the rest stays unpaid on the bill.')" />
            @endunless
            <x-field name="attachment" type="file" :label="__('Bill photo')" :hint="$editing && $bill->attachment ? __('Leave empty to keep the current photo.') : null" />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $back }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ $editing ? __('Save changes') : __('Save bill') }}</button></div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const rows = document.getElementById('itemRows'), total = document.getElementById('itemsTotal');
                const material = @js($material);
                const fmt = n => 'Rs ' + Math.round(n).toLocaleString('en-PK');
                const lineTotal = tr => material
                    ? (+tr.querySelector('[data-qty]').value || 0) * (+tr.querySelector('[data-rate]').value || 0)
                    : (+tr.querySelector('[data-amount]').value || 0);
                const filled = tr => [...tr.querySelectorAll('input')].some(el => el.value.trim() !== '');
                const refresh = () => {
                    let t = 0;
                    [...rows.children].forEach((tr, i) => {
                        tr.querySelector('[data-no]').textContent = i + 1;
                        const a = lineTotal(tr);
                        if (material) { tr.querySelector('[data-line]').textContent = fmt(a); }
                        t += Math.round(a);
                    });
                    total.textContent = fmt(t);
                };
                let next = rows.children.length;
                // Once the last row has a price, a fresh empty row is added below it.
                const ensureBlankRow = () => {
                    const last = rows.lastElementChild;
                    if (!last || lineTotal(last) <= 0) { return; }
                    const tr = last.cloneNode(true);
                    tr.querySelectorAll('input').forEach(el => {
                        el.name = el.name.replace(/items\[\d+]/, `items[${next}]`);
                        el.value = '';
                    });
                    next++;
                    rows.appendChild(tr);
                };
                // Picking an item bought before fills in the price per quantity it was last bought at; it can still be changed.
                const lastRates = @js((object) $lastRates);
                rows.addEventListener('input', e => {
                    if (e.target.matches('[data-item]')) {
                        const rate = lastRates[e.target.value.trim().toLowerCase()];
                        const box = e.target.closest('tr').querySelector('[data-rate]');
                        if (rate !== undefined && (box.value === '' || box.dataset.auto === '1')) { box.value = rate; box.dataset.auto = '1'; }
                    }
                    if (e.target.matches('[data-rate]')) { e.target.dataset.auto = '0'; }
                    ensureBlankRow(); refresh();
                });
                rows.addEventListener('click', e => {
                    const btn = e.target.closest('[data-remove]');
                    if (!btn) { return; }
                    const tr = btn.closest('tr');
                    if (rows.children.length > 1) { tr.remove(); } else { tr.querySelectorAll('input').forEach(el => { el.value = ''; }); }
                    refresh();
                });
                // Empty rows are not sent, so they do not trip the checks.
                rows.closest('form').addEventListener('submit', () => {
                    [...rows.children].forEach(tr => { if (!filled(tr)) { tr.querySelectorAll('input').forEach(el => el.disabled = true); } });
                });
                refresh();
            })();
        </script>
    @endpush
</x-layouts.app>
