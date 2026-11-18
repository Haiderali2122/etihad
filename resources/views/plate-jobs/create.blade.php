@php
    $job ??= null;
    $editing = $job !== null;
    $v = fn (string $field) => $editing ? $job->{$field} : null;
    $processorName = old('processor_name', $editing ? $job->processor?->name : $processors->firstWhere('id', (int) request('processor'))?->name);
    $order = $orders->firstWhere('id', $orderId);
    $bookName = old('book_name', $editing ? $job->book_name : $order?->bookName());
@endphp
<x-layouts.app :title="$editing ? __('Edit plate job') : __('Add plate job')">
    <x-page-head :back="$back" :title="$editing ? __('Edit plate job') : __('Add plate job')" :subtitle="$editing ? __('The same record shows on the Plates and Processors tabs.') : null" />
    <form method="POST" action="{{ $editing ? route('plate-jobs.update', $job) : route('plate-jobs.store') }}" class="card form-card">
        @csrf
        @if ($editing) @method('PUT') <input type="hidden" name="return_to" value="{{ $back }}"> @endif
        <div class="card-b"><div class="form">
            {{-- Type the processor or pick one; a new name is added to Processors. --}}
            <div @class(['field', 'has-err' => $errors->has('party_id') || $errors->has('processor_name')])>
                <label for="f_processor_name">{{ __('Processor') }} <span class="muted">*</span></label>
                <input type="text" id="f_processor_name" name="processor_name" value="{{ $processorName }}" list="processorList" autocomplete="off" maxlength="120" required>
                <datalist id="processorList">@foreach ($processors as $p)<option value="{{ $p->name }}">@endforeach</datalist>
                @error('party_id')<span class="err">{{ $message }}</span>@enderror
            </div>
            {{-- Plates are made before the work order: which book they are for, typed or picked. --}}
            <div class="field">
                <label for="f_book_name">{{ __('For which book') }}</label>
                <input type="text" id="f_book_name" name="book_name" value="{{ $bookName }}" list="bookList" autocomplete="off" maxlength="150">
                <datalist id="bookList">@foreach ($books as $b)<option value="{{ $b }}">@endforeach</datalist>
                @if ($order)<input type="hidden" name="work_order_id" value="{{ $order->id }}"><span class="hint">{{ __('For work order :no', ['no' => $order->number]) }}</span>@endif
            </div>
            <div class="field">
                <label for="f_plate_size">{{ __('Plate size') }}</label>
                <input type="text" id="f_plate_size" name="plate_size" value="{{ old('plate_size', $v('plate_size')) }}" list="sizeList" autocomplete="off" maxlength="40">
                <datalist id="sizeList">@foreach ($sizes as $s)<option value="{{ $s }}">@endforeach</datalist>
            </div>
            <x-field name="plates" type="number" min="1" :label="__('Plates given')" :value="$v('plates')" required />
            <x-field name="rate" type="number" step="0.01" min="0" :label="__('Rate per plate (Rs)')" :value="$editing ? (float) $job->rate : null" required />
            <div class="field"><label>{{ __('Amount') }}</label><div class="calc" style="padding:10px 14px"><div class="big num" id="plateAmount" style="font-size:22px">Rs 0</div></div></div>
            <x-field name="job_on" type="date" :label="__('Date given')" :value="$editing ? $job->job_on->toDateString() : today()->toDateString()" required />
            <x-field name="note" :label="__('Note')" :value="$v('note')" full />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $back }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ $editing ? __('Save changes') : __('Save') }}</button></div>
    </form>

    @if ($editing)
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Payments') }}</h3><div class="sub">{{ __('Rs :p paid of Rs :t · Rs :u unpaid', ['p' => number_format($job->paid_amount), 't' => number_format($job->amount), 'u' => number_format($job->unpaid())]) }}</div></div></div>
            <div class="card-b">@include('partials.bill-payments', ['payable' => $job, 'payUrl' => route('plate-jobs.pay', $job), 'from' => $job->job_on, 'canPay' => auth()->user()->can('plates.manage')])</div>
        </div>
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Plates returned') }}</h3><div class="sub">{{ __(':r of :g returned · :h still with the processor', ['r' => number_format($job->returned_plates), 'g' => number_format($job->plates), 'h' => number_format($job->platesHeld())]) }}</div></div></div>
            <div class="card-b">
                @forelse ($job->returns as $r)
                    <div class="stat-line"><span>{{ $r->returned_on->format('j M Y') }}@if ($r->note) · {{ $r->note }}@endif</span><b>{{ number_format($r->plates) }} {{ __('plates') }} @can('plates.manage')<x-delete-button :action="route('records.destroy', ['plate-return', $r->id])" :label="''" small :danger="false" aria-label="{{ __('Delete') }}" />@endcan</b></div>
                @empty
                    <div class="empty">{{ __('Nothing returned yet') }}</div>
                @endforelse
                @can('plates.manage')@if ($job->platesHeld() > 0)
                    <form method="POST" action="{{ route('plate-jobs.return', $job) }}" class="form" style="margin-top:10px">
                        @csrf @method('PATCH')
                        <x-field name="returned_plates" type="number" min="1" :max="$job->platesHeld()" :label="__('Plates returned')" :value="$job->platesHeld()" required />
                        <x-field name="returned_on" type="date" :label="__('Returned on')" :value="today()->toDateString()" required />
                        <x-field name="note" :label="__('Note')" />
                        <div class="field full"><button class="btn" type="submit" style="justify-content:center"><x-icon name="download" />{{ __('Record return') }}</button></div>
                    </form>
                @endif @endcan
            </div>
        </div>
    @endif
    @push('scripts')
        <script>
            (() => {
                const p = document.getElementById('f_plates'), r = document.getElementById('f_rate'), out = document.getElementById('plateAmount');
                const calc = () => out.textContent = 'Rs ' + Math.round((+p.value || 0) * (+r.value || 0)).toLocaleString('en-PK');
                [p, r].forEach(el => el.addEventListener('input', calc)); calc();
                // A new job starts with the processor's last rate for that plate size (or their last rate); it can be changed.
                const lastRates = @js((object) ($lastRates ?? [])), proc = document.getElementById('f_processor_name'), size = document.getElementById('f_plate_size');
                let auto = r.value === '';
                r.addEventListener('input', () => { auto = false; });
                const fill = () => {
                    const forProc = lastRates[proc.value.trim().toLowerCase()];
                    if (!auto || !forProc) { return; }
                    const rate = forProc[size.value.trim().toLowerCase()] ?? forProc[''];
                    if (rate !== undefined) { r.value = rate; calc(); }
                };
                [proc, size].forEach(el => el.addEventListener('input', fill)); fill();
            })();
        </script>
    @endpush
</x-layouts.app>
