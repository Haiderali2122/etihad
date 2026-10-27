<x-layouts.app :title="__('New loading')">
    <x-page-head :title="__('New loading :n', ['n' => $number])" :subtitle="__('Copies = bundles × copies per bundle + loose copies. Labour = bundles × rate per bundle + rims × rate per rim.')" />
    @if ($buyers->isEmpty())
        <div class="card"><div class="empty"><b>{{ __('No buyers yet') }}</b><a href="{{ route('parties.create') }}">{{ __('Add one under Parties & suppliers as a “Buyer”.') }}</a></div></div>
    @else
        <form method="POST" action="{{ route('dispatches.store') }}" class="card form-card">
            @csrf
            <div class="card-b"><div class="form">
                <x-field name="buyer_id" :label="__('Buyer')" :options="$buyers->pluck('name', 'id')" :value="$order?->buyer_id" :placeholder="__('Choose buyer')" required />
                <x-field name="destination" :label="__('Destination')" placeholder="Urdu Bazaar, Lahore" />
                <x-field name="work_order_id" :label="__('Work order')" :options="$orders->mapWithKeys(fn ($o) => [$o->id => $o->number.' — '.$o->bookFormat->book->name.' ('.number_format($o->copies).')'])" :value="$order?->id" :placeholder="__('Not from an order')" />
                <div class="field" id="bookField">
                    <label for="f_book_format_id">{{ __('Book') }}</label>
                    <select id="f_book_format_id" name="book_format_id">
                        <option value="">{{ __('Choose book') }}</option>
                        @foreach ($books as $book)@foreach ($book->formats as $f)<option value="{{ $f->id }}" @selected((string) old('book_format_id') === (string) $f->id)>{{ $book->name }}{{ $book->formats->count() > 1 ? ' — '.$f->name : '' }}</option>@endforeach @endforeach
                    </select>
                    @error('book_format_id')<span class="err">{{ $message }}</span>@enderror
                </div>
                <x-field name="bundles" type="number" min="0" :label="__('Bundles')" required />
                <x-field name="copies_per_bundle" type="number" min="1" :label="__('Copies per bundle')" required />
                <x-field name="loose_copies" type="number" min="0" :label="__('Loose copies')" value="0" />
                <x-field name="rims" type="number" step="0.01" min="0" :label="__('Rims (for labour)')" />
                <x-field name="labour_per_bundle" type="number" step="0.01" min="0" :label="__('Labour per bundle (Rs)')" />
                <x-field name="labour_per_rim" type="number" step="0.01" min="0" :label="__('Labour per rim (Rs)')" />
                <div class="field full"><div class="calc">
                    <div class="lbl">{{ __('This load') }}</div>
                    <div class="big num" id="loadCopies">0 {{ __('copies') }}</div>
                    <div class="eq" id="loadLabour">{{ __('Labour') }}: Rs 0</div>
                </div>@error('copies')<span class="err">{{ $message }}</span>@enderror</div>
                <x-field name="labour_paid_to" :label="__('Labour paid to')" />
                <x-field name="truck_no" :label="__('Truck / vehicle no.')" />
                <x-field name="dispatched_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                <x-field name="note" :label="__('Note')" />
            </div></div>
            <div class="form-actions"><a class="btn" href="{{ route('dispatches.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="truck" />{{ __('Save loading') }}</button></div>
        </form>
        @push('scripts')
            <script>
                (() => {
                    const $ = id => document.getElementById(id), n = id => +$(id).value || 0;
                    const calc = () => {
                        $('loadCopies').textContent = (n('f_bundles') * n('f_copies_per_bundle') + n('f_loose_copies')).toLocaleString('en-PK') + ' {{ __('copies') }}';
                        $('loadLabour').textContent = '{{ __('Labour') }}: Rs ' + Math.round(n('f_bundles') * n('f_labour_per_bundle') + n('f_rims') * n('f_labour_per_rim')).toLocaleString('en-PK');
                    };
                    const book = () => { $('bookField').hidden = !!$('f_work_order_id').value; };
                    ['f_bundles', 'f_copies_per_bundle', 'f_loose_copies', 'f_rims', 'f_labour_per_bundle', 'f_labour_per_rim'].forEach(id => $(id).addEventListener('input', calc));
                    $('f_work_order_id').addEventListener('change', book);
                    calc(); book();
                })();
            </script>
        @endpush
    @endif
</x-layouts.app>
