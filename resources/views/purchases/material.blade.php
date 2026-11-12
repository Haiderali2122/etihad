@php
    $other = \App\Support\OtherOption::Value;
    $rows = array_values(old('items', [])) ?: [['inventory_item_id' => $items->count() === 1 ? $items->first()->id : '', 'quantity' => '', 'rate' => '']];
@endphp
<x-layouts.app :title="__('Buy :material', ['material' => $category->localName()])">
    <x-page-head :back="$back" :title="__('Buy :material', ['material' => $category->localName()])" :subtitle="__('One bill: the supplier, then a line for each item bought with its quantity and rate. The receipt shows every line and the total; it comes off the manager\'s cash and shows in Cash-out.')" />
    <form method="POST" action="{{ route('materials.purchase.store', $category) }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        <div class="card-b"><div class="form">
            <x-field name="purchased_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
            <x-field-other name="party_id" :label="__('Supplier')" :options="$parties->pluck('name', 'id')" :placeholder="__('Choose supplier')" required />

            <div @class(['field', 'full', 'has-err' => $errors->has('items') || $errors->has('items.*')])>
                <label>{{ __('Items') }} <span class="muted">*</span></label>
                <div class="tbl-wrap"><table class="fmt-table no-stack">
                    <thead><tr><th style="min-width:200px">{{ __('Item') }}</th><th class="r" style="width:130px">{{ __('Quantity') }}</th><th class="r" style="width:130px">{{ __('Rate (Rs)') }}</th><th class="r" style="width:130px">{{ __('Amount') }}</th><th style="width:44px"></th></tr></thead>
                    <tbody id="itemRows">
                    @foreach ($rows as $i => $row)
                        @php $isOther = (string) ($row['inventory_item_id'] ?? '') === $other; @endphp
                        <tr>
                            <td>
                                <select name="items[{{ $i }}][inventory_item_id]" aria-label="{{ __('Item') }}" required data-item>
                                    <option value="">{{ __('Choose item') }}</option>
                                    @foreach ($items as $it)<option value="{{ $it->id }}" @selected((string) ($row['inventory_item_id'] ?? '') === (string) $it->id)>{{ $it->localName() }} ({{ __($it->unit) }})</option>@endforeach
                                    <option value="{{ $other }}" @selected($isOther)>{{ __('Other… (type a new one)') }}</option>
                                </select>
                                <input type="text" name="items[{{ $i }}][item_other]" value="{{ $row['item_other'] ?? '' }}" maxlength="80" placeholder="{{ __('Type the name') }}" aria-label="{{ __('New item') }}" @if (! $isOther) hidden @else required @endif style="margin-top:6px" data-item-other>
                            </td>
                            <td><input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}" aria-label="{{ __('Quantity') }}" required style="text-align:end" data-qty></td>
                            <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][rate]" value="{{ $row['rate'] ?? '' }}" aria-label="{{ __('Rate (Rs)') }}" required style="text-align:end" data-rate></td>
                            <td class="r num" data-line>Rs 0</td>
                            <td><button type="button" class="icon-btn danger" data-remove aria-label="{{ __('Remove item') }}"><x-icon name="x" /></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="3"><button type="button" class="btn sm" id="addItem"><x-icon name="plus" />{{ __('Add item') }}</button></td><td class="r num"><b id="itemsTotal">Rs 0</b></td><td></td></tr></tfoot>
                </table></div>
                @error('items')<span class="err">{{ $message }}</span>@enderror
                @foreach ($errors->get('items.*') as $messages)<span class="err">{{ $messages[0] }}</span>@endforeach
            </div>

            <x-field name="bill_no" :label="__('Bill no.')" />
            <x-field name="attachment" type="file" :label="__('Bill photo / PDF')" />
            <x-field name="note" :label="__('Note')" full />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $back }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save purchase') }}</button></div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const rows = document.getElementById('itemRows'), total = document.getElementById('itemsTotal'), other = @js($other);
                const fmt = n => 'Rs ' + Math.round(n).toLocaleString('en-PK');
                const sum = () => {
                    let t = 0;
                    rows.querySelectorAll('tr').forEach(tr => {
                        const a = (+tr.querySelector('[data-qty]').value || 0) * (+tr.querySelector('[data-rate]').value || 0);
                        tr.querySelector('[data-line]').textContent = fmt(a);
                        t += Math.round(a);
                    });
                    total.textContent = fmt(t);
                };
                let next = rows.children.length;
                document.getElementById('addItem').addEventListener('click', () => {
                    const tr = rows.lastElementChild.cloneNode(true);
                    tr.querySelectorAll('input, select').forEach(el => {
                        el.name = el.name.replace(/items\[\d+]/, `items[${next}]`);
                        if (el.tagName === 'INPUT') { el.value = ''; }
                        if (el.tagName === 'SELECT') { el.value = ''; }
                    });
                    const box = tr.querySelector('[data-item-other]');
                    box.hidden = true; box.required = false;
                    next++;
                    rows.appendChild(tr);
                    tr.querySelector('[data-item]').focus();
                    sum();
                });
                rows.addEventListener('click', e => {
                    const btn = e.target.closest('[data-remove]');
                    if (btn && rows.children.length > 1) { btn.closest('tr').remove(); sum(); }
                });
                // "Other…" shows a box to type a new item; it is kept under this material for next time.
                rows.addEventListener('change', e => {
                    if (!e.target.matches('[data-item]')) { return; }
                    const box = e.target.closest('td').querySelector('[data-item-other]');
                    box.hidden = e.target.value !== other;
                    box.required = !box.hidden;
                    if (!box.hidden) { box.focus(); }
                });
                rows.addEventListener('input', sum);
                sum();
            })();
        </script>
    @endpush
</x-layouts.app>
