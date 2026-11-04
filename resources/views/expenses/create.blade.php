@php
    $editing = $expense->exists;
    $heading = $editing ? __('Edit expense :no', ['no' => $expense->receiptNumber()]) : ($category ? __('Payment for :material', ['material' => $category->localName()]) : __('Add expense'));
    $rows = old('items', $editing
        ? $expense->items->map(fn ($i) => ['expense_head_id' => $i->expense_head_id ?? $expense->expense_head_id, 'paid_to' => $i->paid_to ?? $expense->paid_to, 'description' => $i->description, 'amount' => $i->amount])->all()
        : []);
    $rows = array_values($rows) ?: [['expense_head_id' => $expense->expense_head_id, 'paid_to' => '', 'description' => '', 'amount' => '']];
    $other = \App\Support\OtherOption::Value;
@endphp
<x-layouts.app :title="$heading">
    <x-page-head :back="$back" :title="$heading" :subtitle="__('Add a line for each payment: its head, who was paid and how much. The receipt shows every line and the total. Spends within the daily limit are approved straight away; anything over goes to the owner.')" />
    <form method="POST" action="{{ $editing ? route('expenses.update', $expense) : route('expenses.store') }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        @if ($editing) @method('PUT') @endif
        @if ($category)<input type="hidden" name="inventory_category_id" value="{{ $category->id }}">@endif
        <div class="card-b"><div class="form">
            <x-field name="spent_on" type="date" :label="__('Date')" :value="$expense->spent_on?->toDateString() ?? today()->toDateString()" required />
            <x-field name="machine_id" :label="__('For machine (optional)')" :options="$machines->mapWithKeys(fn ($m) => [$m->id => $m->code.' '.$m->name])" :value="$expense->machine_id" :placeholder="__('Not for a machine')" />

            <div @class(['field', 'full', 'has-err' => $errors->has('items') || $errors->has('items.*')])>
                <label>{{ __('Payments') }} <span class="muted">*</span></label>
                <div class="tbl-wrap"><table class="fmt-table no-stack">
                    <thead><tr><th style="min-width:180px">{{ __('Head') }}</th><th style="min-width:160px">{{ __('Paid to') }}</th><th style="min-width:160px">{{ __('What for (optional)') }}</th><th class="r" style="width:140px">{{ __('Amount (Rs)') }}</th><th style="width:44px"></th></tr></thead>
                    <tbody id="itemRows">
                    @foreach ($rows as $i => $row)
                        @php $isOther = (string) ($row['expense_head_id'] ?? '') === $other; @endphp
                        <tr>
                            <td>
                                <select name="items[{{ $i }}][expense_head_id]" aria-label="{{ __('Head') }}" required data-head>
                                    <option value="">{{ __('Choose head') }}</option>
                                    @foreach ($heads as $h)<option value="{{ $h->id }}" @selected((string) ($row['expense_head_id'] ?? '') === (string) $h->id)>{{ $h->localName() }}</option>@endforeach
                                    <option value="{{ $other }}" @selected($isOther)>{{ __('Other… (type a new one)') }}</option>
                                </select>
                                <input type="text" name="items[{{ $i }}][head_other]" value="{{ $row['head_other'] ?? '' }}" maxlength="80" placeholder="{{ __('Type the name') }}" aria-label="{{ __('New head') }}" @if (! $isOther) hidden @else required @endif style="margin-top:6px" data-head-other>
                            </td>
                            <td><input type="text" name="items[{{ $i }}][paid_to]" value="{{ $row['paid_to'] ?? '' }}" maxlength="120" list="payees" aria-label="{{ __('Paid to') }}" required></td>
                            <td><input type="text" name="items[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" maxlength="255" aria-label="{{ __('What for (optional)') }}"></td>
                            <td><input type="number" min="1" name="items[{{ $i }}][amount]" value="{{ $row['amount'] ?? '' }}" aria-label="{{ __('Amount (Rs)') }}" required style="text-align:end" data-amount></td>
                            <td><button type="button" class="icon-btn danger" data-remove aria-label="{{ __('Remove item') }}"><x-icon name="x" /></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="3"><button type="button" class="btn sm" id="addItem"><x-icon name="plus" />{{ __('Add item') }}</button></td><td class="r num"><b id="itemsTotal">Rs 0</b></td><td></td></tr></tfoot>
                </table></div>
                <datalist id="payees">@foreach ($payees as $p)<option value="{{ $p }}">@endforeach</datalist>
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
                const rows = document.getElementById('itemRows'), total = document.getElementById('itemsTotal'), other = @js($other);
                const sum = () => {
                    const t = [...rows.querySelectorAll('[data-amount]')].reduce((s, el) => s + (+el.value || 0), 0);
                    total.textContent = 'Rs ' + t.toLocaleString('en-PK');
                };
                let next = rows.children.length;
                document.getElementById('addItem').addEventListener('click', () => {
                    const tr = rows.lastElementChild.cloneNode(true);
                    tr.querySelectorAll('input, select').forEach(el => {
                        el.name = el.name.replace(/items\[\d+]/, `items[${next}]`);
                        if (el.matches('[data-amount], [data-head-other]') || el.name.endsWith('[description]')) { el.value = ''; }
                    });
                    const box = tr.querySelector('[data-head-other]');
                    box.hidden = tr.querySelector('[data-head]').value !== other;
                    next++;
                    rows.appendChild(tr);
                    tr.querySelector('[data-head]').focus();
                    sum();
                });
                rows.addEventListener('click', e => {
                    const btn = e.target.closest('[data-remove]');
                    if (btn && rows.children.length > 1) { btn.closest('tr').remove(); sum(); }
                });
                // "Other…" shows a box to type the new head; it is saved to the list for next time.
                rows.addEventListener('change', e => {
                    if (!e.target.matches('[data-head]')) { return; }
                    const box = e.target.closest('td').querySelector('[data-head-other]');
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
