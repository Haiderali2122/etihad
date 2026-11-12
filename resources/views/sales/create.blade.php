@php
    $rows = array_values(array_filter(old('items', []), fn ($r) => collect(['item', 'quantity', 'rate'])->contains(fn ($f) => filled($r[$f] ?? null))));
    $rows[] = ['item' => '', 'quantity' => '', 'rate' => ''];
@endphp
<x-layouts.app :title="__('Sell :name', ['name' => $category->localName()])">
    <x-page-head :back="$back" :title="__('Sell :name', ['name' => $category->localName()])" :subtitle="__('One row per item sold: its quantity and price per quantity; the total is worked out. Sales are money coming in, so they stay on this tab and do not go to Cash-out.')" />
    <form method="POST" action="{{ route('sales.store', $category) }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        <div class="card-b"><div class="form">
            <x-field name="sold_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />

            <div @class(['field', 'full', 'has-err' => $errors->has('items') || $errors->has('items.*')])>
                <div class="tbl-wrap"><table class="fmt-table no-stack">
                    <thead><tr><th style="width:44px">#</th><th style="min-width:200px">{{ __('Item') }}</th><th class="r" style="width:120px">{{ __('Quantity') }}</th><th class="r" style="width:140px">{{ __('Price per quantity') }}</th><th class="r" style="width:140px">{{ __('Total') }}</th><th style="width:44px"></th></tr></thead>
                    <tbody id="itemRows">
                    @foreach ($rows as $i => $row)
                        <tr>
                            <td class="num" data-no>{{ $loop->iteration }}</td>
                            <td><input type="text" name="items[{{ $i }}][item]" value="{{ $row['item'] ?? '' }}" list="itemList" maxlength="120" autocomplete="off" aria-label="{{ __('Item') }}"></td>
                            <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}" aria-label="{{ __('Quantity') }}" style="text-align:end" data-qty></td>
                            <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][rate]" value="{{ $row['rate'] ?? '' }}" aria-label="{{ __('Price per quantity') }}" style="text-align:end" data-rate></td>
                            <td class="r num" data-line>Rs 0</td>
                            <td><button type="button" class="icon-btn danger" data-remove aria-label="{{ __('Remove item') }}"><x-icon name="x" /></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="4" style="font-weight:700">{{ __('Total') }}</td><td class="r num"><b id="itemsTotal">Rs 0</b></td><td></td></tr></tfoot>
                </table></div>
                <datalist id="itemList">@foreach ($suggestions as $s)<option value="{{ $s }}">@endforeach</datalist>
                @error('items')<span class="err">{{ __('Add at least one item with its quantity and price.') }}</span>@enderror
                @foreach ($errors->get('items.*') as $messages)<span class="err">{{ $messages[0] }}</span>@endforeach
            </div>

            <x-field name="attachment" type="file" :label="__('Receipt photo')" />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $back }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save sale') }}</button></div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const rows = document.getElementById('itemRows'), total = document.getElementById('itemsTotal');
                const fmt = n => 'Rs ' + Math.round(n).toLocaleString('en-PK');
                const lineTotal = tr => (+tr.querySelector('[data-qty]').value || 0) * (+tr.querySelector('[data-rate]').value || 0);
                const filled = tr => [...tr.querySelectorAll('input')].some(el => el.value.trim() !== '');
                const refresh = () => {
                    let t = 0;
                    [...rows.children].forEach((tr, i) => {
                        tr.querySelector('[data-no]').textContent = i + 1;
                        const a = lineTotal(tr);
                        tr.querySelector('[data-line]').textContent = fmt(a);
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
                    tr.querySelectorAll('input').forEach(el => { el.name = el.name.replace(/items\[\d+]/, `items[${next}]`); el.value = ''; });
                    next++;
                    rows.appendChild(tr);
                };
                rows.addEventListener('input', () => { ensureBlankRow(); refresh(); });
                rows.addEventListener('click', e => {
                    const btn = e.target.closest('[data-remove]');
                    if (!btn) { return; }
                    const tr = btn.closest('tr');
                    if (rows.children.length > 1) { tr.remove(); } else { tr.querySelectorAll('input').forEach(el => { el.value = ''; }); }
                    refresh();
                });
                rows.closest('form').addEventListener('submit', () => {
                    [...rows.children].forEach(tr => { if (!filled(tr)) { tr.querySelectorAll('input').forEach(el => el.disabled = true); } });
                });
                refresh();
            })();
        </script>
    @endpush
</x-layouts.app>
