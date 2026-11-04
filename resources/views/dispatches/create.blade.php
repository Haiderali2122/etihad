@php
    $editing = isset($dispatch) && $dispatch->exists;
    $v = fn (string $field) => $editing ? $dispatch->{$field} : null;
@endphp
<x-layouts.app :title="$editing ? __('Edit loading :n', ['n' => $dispatch->number]) : __('New loading')">
    <x-page-head :back="$back" :title="$editing ? __('Edit loading :n', ['n' => $dispatch->number]) : __('New loading :n', ['n' => $number])" :subtitle="__('Truck loading after binding. Copies = bundles × copies per bundle + loose copies. Labour = bundles × rate per bundle.')" />
    @if ($buyers->isEmpty())
        <div class="card"><div class="empty"><b>{{ __('No buyers yet') }}</b><a href="{{ route('parties.create', ['kind' => 'buyer', 'return' => request()->fullUrl()]) }}">{{ __('Add a buyer') }}</a></div></div>
    @else
        <form method="POST" action="{{ $editing ? route('dispatches.update', $dispatch) : route('dispatches.store') }}" enctype="multipart/form-data" class="card form-card">
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="card-b"><div class="form">
                <x-field name="buyer_id" :label="__('Buyer')" :options="$buyers->pluck('name', 'id')" :value="$editing ? $dispatch->buyer_id : $buyerId" :placeholder="__('Choose buyer')" required />
                <x-field name="destination" :label="__('Destination')" :value="$v('destination')" placeholder="Urdu Bazaar, Lahore" />
                <x-field name="work_order_id" :label="__('Work order')" :options="$orders->mapWithKeys(fn ($o) => [$o->id => $o->number.' — '.$o->bookFormat->book->name.' ('.number_format($o->copies).')'])" :value="$editing ? $dispatch->work_order_id : $order?->id" :placeholder="__('Not from an order')" />
                <div class="field" id="bookField">
                    <label for="f_book_format_id">{{ __('Book') }}</label>
                    <select id="f_book_format_id" name="book_format_id">
                        <option value="">{{ __('Choose book') }}</option>
                        @foreach ($books as $book)@foreach ($book->formats as $f)<option value="{{ $f->id }}" @selected((string) old('book_format_id', $v('book_format_id')) === (string) $f->id)>{{ $book->name }}{{ $book->formats->count() > 1 ? ' — '.$f->name : '' }}</option>@endforeach @endforeach
                    </select>
                    @error('book_format_id')<span class="err">{{ $message }}</span>@enderror
                </div>
                <x-field name="bundles" type="number" min="0" :label="__('Bundles')" :value="$v('bundles')" required />
                <x-field name="copies_per_bundle" type="number" min="1" :label="__('Copies per bundle')" :value="$v('copies_per_bundle')" required />
                <x-field name="loose_copies" type="number" min="0" :label="__('Loose copies')" :value="$editing ? $dispatch->loose_copies : 0" />
                <x-field name="labour_per_bundle" type="number" step="0.01" min="0" :label="__('Labour per bundle (Rs)')" :value="$editing ? (float) $dispatch->labour_per_bundle : null" />
                <div class="field full"><div class="calc">
                    <div class="lbl">{{ __('This load') }}</div>
                    <div class="big num" id="loadCopies">0 {{ __('copies') }}</div>
                    <div class="eq" id="loadLabour">{{ __('Labour') }}: Rs 0</div>
                </div>@error('copies')<span class="err">{{ $message }}</span>@enderror</div>
                <x-field name="labour_paid_to" :label="__('Labour paid to')" :value="$v('labour_paid_to')" />
                <x-field name="truck_no" :label="__('Truck / vehicle no.')" :value="$v('truck_no')" />
                <x-field name="dispatched_on" type="date" :label="__('Date')" :value="$editing ? $dispatch->dispatched_on->toDateString() : today()->toDateString()" required />
                <x-field name="note" :label="__('Note')" :value="$v('note')" />
                <x-field name="truck_front" type="file" :label="__('Truck photo, front')" accept="image/*" :hint="$editing && $dispatch->truck_front ? __('Leave empty to keep the current photo.') : null" />
                <x-field name="truck_back" type="file" :label="__('Truck photo, back')" accept="image/*" :hint="$editing && $dispatch->truck_back ? __('Leave empty to keep the current photo.') : null" />
                <div @class(['field', 'full', 'has-err' => $errors->has('files') || $errors->has('files.*')])>
                    <label for="f_files">{{ __('Other files (slips, bilty, bills)') }}</label>
                    <input id="f_files" type="file" name="files[]" multiple accept="image/*,application/pdf">
                    @if ($editing && $dispatch->files)<span class="hint">{{ trans_choice(':count file already attached; new ones are added to it.|:count files already attached; new ones are added to them.', count($dispatch->files)) }}</span>@endif
                    @error('files')<span class="err">{{ $message }}</span>@enderror
                    @error('files.*')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div></div>
            <div class="form-actions"><a class="btn" href="{{ $back }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="truck" />{{ $editing ? __('Save changes') : __('Save loading') }}</button></div>
        </form>
        @push('scripts')
            <script>
                (() => {
                    const $ = id => document.getElementById(id), n = id => +$(id).value || 0;
                    const calc = () => {
                        $('loadCopies').textContent = (n('f_bundles') * n('f_copies_per_bundle') + n('f_loose_copies')).toLocaleString('en-PK') + ' {{ __('copies') }}';
                        $('loadLabour').textContent = '{{ __('Labour') }}: Rs ' + Math.round(n('f_bundles') * n('f_labour_per_bundle')).toLocaleString('en-PK');
                    };
                    const book = () => { $('bookField').hidden = !!$('f_work_order_id').value; };
                    ['f_bundles', 'f_copies_per_bundle', 'f_loose_copies', 'f_labour_per_bundle'].forEach(id => $(id).addEventListener('input', calc));
                    $('f_work_order_id').addEventListener('change', book);
                    calc(); book();
                })();
            </script>
        @endpush
    @endif
</x-layouts.app>
