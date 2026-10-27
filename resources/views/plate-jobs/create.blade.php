<x-layouts.app :title="__('Add plate job')">
    <x-page-head :title="__('Add plate job')" />
    @if ($processors->isEmpty())
        <div class="card"><div class="empty"><b>{{ __('No plate processors yet') }}</b><a href="{{ route('parties.create') }}">{{ __('Add one under Parties & suppliers as a “Plate processor”.') }}</a></div></div>
    @else
        <form method="POST" action="{{ route('plate-jobs.store') }}" class="card form-card">
            @csrf
            <div class="card-b"><div class="form">
                <x-field name="party_id" :label="__('Processor')" :options="$processors->pluck('name', 'id')" :placeholder="__('Choose processor')" required />
                <x-field name="work_order_id" :label="__('Work order (optional)')" :options="$orders->mapWithKeys(fn ($o) => [$o->id => $o->number.' — '.$o->bookFormat->book->name])" :value="$orderId" :placeholder="__('Not for an order')" />
                <x-field name="plates" type="number" min="1" :label="__('Plates')" required />
                <x-field name="rate" type="number" step="0.01" min="0" :label="__('Rate per plate (Rs)')" required />
                <div class="field"><label>{{ __('Amount') }}</label><div class="calc" style="padding:10px 14px"><div class="big num" id="plateAmount" style="font-size:22px">Rs 0</div></div></div>
                <x-field name="job_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                <x-field name="note" :label="__('Note')" full />
            </div></div>
            <div class="form-actions"><a class="btn" href="{{ route('plate-jobs.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
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
