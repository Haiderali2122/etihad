@php
    $editing = $purchase->exists;
    $v = fn (string $f, $default = null) => old($f, $editing ? $purchase->{$f} : $default);
@endphp
<x-layouts.app :title="$editing ? __('Edit plate purchase') : __('Add plate purchase')">
    <x-page-head :back="route('plate-stock.index')" :title="$editing ? __('Edit plate purchase') : __('Add plate purchase')" :subtitle="__('The plates are added to stock for their size. The bill is paid later, in parts or all at once.')" />
    <form method="POST" action="{{ $editing ? route('plate-stock.update', $purchase) : route('plate-stock.store') }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="purchased_on" type="date" :label="__('Date')" :value="$purchase->purchased_on?->toDateString() ?? today()->toDateString()" required />
            {{-- Typed or picked; a new name joins Parties as a supplier. --}}
            <div @class(['field', 'has-err' => $errors->has('supplier')])>
                <label for="f_supplier">{{ __('Supplier') }}</label>
                <input type="text" id="f_supplier" name="supplier" value="{{ $v('supplier') }}" list="supplierList" autocomplete="off" maxlength="120">
                <datalist id="supplierList">@foreach ($suppliers as $s)<option value="{{ $s }}">@endforeach</datalist>
                @error('supplier')<span class="err">{{ $message }}</span>@enderror
            </div>
            <div @class(['field', 'has-err' => $errors->has('plate_size')])>
                <label for="f_plate_size">{{ __('Plate size') }} <span class="muted">*</span></label>
                <input type="text" id="f_plate_size" name="plate_size" value="{{ $v('plate_size') }}" list="sizeList" autocomplete="off" maxlength="40" required>
                <datalist id="sizeList">@foreach ($sizes as $s)<option value="{{ $s }}">@endforeach</datalist>
                @error('plate_size')<span class="err">{{ $message }}</span>@enderror
            </div>
            <x-field name="plates" type="number" min="1" step="1" :label="__('Number of plates')" :value="$v('plates')" required />
            <x-field name="rate" type="number" min="0" step="0.01" :label="__('Price per plate (Rs)')" :value="$editing ? (float) $purchase->rate : old('rate')" required />
            <div class="field"><label>{{ __('Total') }}</label><div class="calc" style="padding:10px 14px"><div class="big num" id="plateTotal" style="font-size:22px">Rs 0</div></div></div>
            @unless ($editing)
                <x-field name="paid_now" type="number" min="0" step="1" :label="__('Paid now (Rs)')" :hint="__('Leave empty if nothing is paid yet; the rest stays unpaid on the bill.')" />
            @endunless
            <x-field name="bill_no" :label="__('Bill no.')" :value="$v('bill_no')" />
            <x-field name="attachment" type="file" :label="__('Bill photo')" :hint="$editing && $purchase->attachment ? __('Leave empty to keep the current photo.') : null" />
            <x-field name="note" :label="__('Note')" :value="$v('note')" full />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('plate-stock.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ $editing ? __('Save changes') : __('Save') }}</button></div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const n = document.getElementById('f_plates'), r = document.getElementById('f_rate'), out = document.getElementById('plateTotal');
                const size = document.getElementById('f_plate_size'), supplier = document.getElementById('f_supplier');
                const calc = () => out.textContent = 'Rs ' + Math.round((+n.value || 0) * (+r.value || 0)).toLocaleString('en-PK');
                // The last price for this size (from this supplier if bought from them before) fills in; it can be changed.
                const lastRates = @js((object) $lastRates);
                let auto = r.value === '';
                r.addEventListener('input', () => { auto = false; });
                const fill = () => {
                    const forSize = lastRates[size.value.trim().toLowerCase()];
                    if (!auto || !forSize) { return; }
                    const rate = forSize[supplier.value.trim().toLowerCase()] ?? forSize[''];
                    if (rate !== undefined) { r.value = rate; calc(); }
                };
                [n, r].forEach(el => el.addEventListener('input', calc));
                [size, supplier].forEach(el => el.addEventListener('input', fill));
                calc();
            })();
        </script>
    @endpush
</x-layouts.app>
