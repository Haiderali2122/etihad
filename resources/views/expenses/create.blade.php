@php
    $editing = $expense->exists;
    $heading = $editing ? __('Edit expense :no', ['no' => $expense->receiptNumber()]) : ($category ? __('Payment for :material', ['material' => $category->localName()]) : __('Add expense'));
    $rows = old('items', $editing ? $expense->items->map(fn ($i) => ['description' => $i->description, 'amount' => $i->amount])->all() : []);
    $rows = array_values($rows) ?: [['description' => '', 'amount' => '']];
@endphp
<x-layouts.app :title="$heading">
    <x-page-head :back="$back" :title="$heading" :subtitle="__('Add each item with its amount; the total is the expense. Spends within the daily limit are approved straight away; anything over goes to the owner.')" />
    <form method="POST" action="{{ $editing ? route('expenses.update', $expense) : route('expenses.store') }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        @if ($editing) @method('PUT') @endif
        @if ($category)<input type="hidden" name="inventory_category_id" value="{{ $category->id }}">@endif
        <div class="card-b"><div class="form">
            <x-field name="spent_on" type="date" :label="__('Date')" :value="$expense->spent_on?->toDateString() ?? today()->toDateString()" required />
            <x-field-other name="expense_head_id" :label="__('Head')" :options="$heads->mapWithKeys(fn ($h) => [$h->id => $h->localName()])" :value="$expense->expense_head_id" :placeholder="__('Choose head')" required />
            <x-field name="paid_to" :label="__('Paid to')" :value="$expense->paid_to" required />
            <x-field name="machine_id" :label="__('For machine (optional)')" :options="$machines->mapWithKeys(fn ($m) => [$m->id => $m->code.' '.$m->name])" :value="$expense->machine_id" :placeholder="__('Not for a machine')" />

            <div @class(['field', 'full', 'has-err' => $errors->has('items') || $errors->has('items.*')])>
                <label>{{ __('Items') }} <span class="muted">*</span></label>
                <div class="tbl-wrap"><table class="fmt-table">
                    <thead><tr><th>{{ __('Item / what for') }}</th><th class="r" style="width:160px">{{ __('Amount (Rs)') }}</th><th style="width:44px"></th></tr></thead>
                    <tbody id="itemRows">
                    @foreach ($rows as $i => $row)
                        <tr>
                            <td><input type="text" name="items[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" maxlength="255" aria-label="{{ __('Item') }}" required></td>
                            <td><input type="number" min="1" name="items[{{ $i }}][amount]" value="{{ $row['amount'] ?? '' }}" aria-label="{{ __('Amount (Rs)') }}" required style="text-align:end" data-amount></td>
                            <td><button type="button" class="icon-btn danger" data-remove aria-label="{{ __('Remove item') }}"><x-icon name="x" /></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td><button type="button" class="btn sm" id="addItem"><x-icon name="plus" />{{ __('Add item') }}</button></td><td class="r num"><b id="itemsTotal">Rs 0</b></td><td></td></tr></tfoot>
                </table></div>
                @error('items')<span class="err">{{ $message }}</span>@enderror
                @foreach ($errors->get('items.*') as $messages)<span class="err">{{ $messages[0] }}</span>@endforeach
            </div>

            <x-field name="attachment" type="file" :label="__('Receipt photo')" :hint="$editing && $expense->attachment ? __('Leave empty to keep the current photo.') : null" />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $back }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ $editing ? __('Save changes') : __('Save expense') }}</button></div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const rows = document.getElementById('itemRows'), total = document.getElementById('itemsTotal');
                const sum = () => {
                    const t = [...rows.querySelectorAll('[data-amount]')].reduce((s, el) => s + (+el.value || 0), 0);
                    total.textContent = 'Rs ' + t.toLocaleString('en-PK');
                };
                let next = rows.children.length;
                document.getElementById('addItem').addEventListener('click', () => {
                    const tr = rows.firstElementChild.cloneNode(true);
                    tr.querySelectorAll('input').forEach(el => { el.value = ''; el.name = el.name.replace(/items\[\d+]/, `items[${next}]`); });
                    next++;
                    rows.appendChild(tr);
                    tr.querySelector('input').focus();
                });
                rows.addEventListener('click', e => {
                    const btn = e.target.closest('[data-remove]');
                    if (btn && rows.children.length > 1) { btn.closest('tr').remove(); sum(); }
                });
                rows.addEventListener('input', sum);
                sum();
            })();
        </script>
    @endpush
</x-layouts.app>
