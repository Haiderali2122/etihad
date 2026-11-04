@php
    $job ??= null;
    $editing = $job !== null;
    $v = fn (string $field) => $editing ? $job->{$field} : null;
@endphp
<x-layouts.app :title="$editing ? __('Edit plate job') : __('Add plate job')">
    <x-page-head :back="$back" :title="$editing ? __('Edit plate job') : __('Add plate job')" :subtitle="$editing ? __('The same record shows on the Plates and Processors tabs.') : null" />
    @if ($processors->isEmpty())
        <div class="card"><div class="empty"><b>{{ __('No plate processors yet') }}</b><a href="{{ route('processors.create', ['return' => request()->fullUrl()]) }}">{{ __('Add a plate processor') }}</a></div></div>
    @else
        <form method="POST" action="{{ $editing ? route('plate-jobs.update', $job) : route('plate-jobs.store') }}" class="card form-card">
            @csrf
            @if ($editing) @method('PUT') <input type="hidden" name="return_to" value="{{ $back }}"> @endif
            <div class="card-b"><div class="form">
                <x-field name="party_id" :label="__('Processor')" :options="$processors->pluck('name', 'id')" :value="$v('party_id') ?? request('processor')" :placeholder="__('Choose processor')" required />
                <x-field name="work_order_id" :label="__('Work order (optional)')" :options="$orders->mapWithKeys(fn ($o) => [$o->id => $o->number.' — '.$o->bookFormat->book->name])" :value="$orderId" :placeholder="__('Not for an order')" />
                <x-field name="plates" type="number" min="1" :label="__('Plates given')" :value="$v('plates')" required />
                <x-field name="rate" type="number" step="0.01" min="0" :label="__('Rate per plate (Rs)')" :value="$editing ? (float) $job->rate : null" required />
                <div class="field"><label>{{ __('Amount') }}</label><div class="calc" style="padding:10px 14px"><div class="big num" id="plateAmount" style="font-size:22px">Rs 0</div></div></div>
                <x-field name="job_on" type="date" :label="__('Date')" :value="$editing ? $job->job_on->toDateString() : today()->toDateString()" required />
                @if ($editing)
                    <x-field name="returned_plates" type="number" min="0" :label="__('Plates returned')" :value="$job->returned_plates" required />
                    <x-field name="paid_on" type="date" :label="__('Paid on')" :value="$job->paid_on?->toDateString()" :hint="__('Leave empty while unpaid.')" />
                    <x-field name="payment_note" :label="__('Payment note')" :value="$job->payment_note" />
                @endif
                <x-field name="note" :label="__('Note')" :value="$v('note')" full />
            </div></div>
            <div class="form-actions"><a class="btn" href="{{ $back }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ $editing ? __('Save changes') : __('Save') }}</button></div>
        </form>
        @push('scripts')
            <script>
                (() => {
                    const p = document.getElementById('f_plates'), r = document.getElementById('f_rate'), out = document.getElementById('plateAmount');
                    const calc = () => out.textContent = 'Rs ' + Math.round((+p.value || 0) * (+r.value || 0)).toLocaleString('en-PK');
                    [p, r].forEach(el => el.addEventListener('input', calc)); calc();
                })();
            </script>
        @endpush
    @endif
</x-layouts.app>
