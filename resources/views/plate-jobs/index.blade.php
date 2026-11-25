@php
    $toPay = max(0, $unpaidTotal);
    $who = $processor?->name;
@endphp
<x-layouts.app :title="__('Plates')">
    <x-page-head :title="__('Plate processing')" :subtitle="__('Plates made by processors: their bills, the money given to them (anything extra is kept as advance), and what is left to pay.')">
        <x-slot:actions><a class="btn" href="{{ route('processors.index') }}"><x-icon name="users" />{{ __('Processors') }}</a>@can('plates.manage')<a class="btn primary" href="{{ route('plate-jobs.create', array_filter(['processor' => $processor?->id])) }}"><x-icon name="plus" />{{ __('Add plate job') }}</a>@endcan</x-slot:actions>
    </x-page-head>
    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    {{-- One processor or all of them. --}}
    @if ($processors->count() > 1)
        <div class="tabs" style="margin-bottom:14px">
            <a href="{{ route('plate-jobs.index', ['show' => $show]) }}" @class(['tab', 'on' => ! $processor]) style="text-decoration:none">{{ __('All processors') }}</a>
            @foreach ($processors as $p)
                <a href="{{ route('plate-jobs.index', ['processor' => $p->id, 'show' => $show]) }}" @class(['tab', 'on' => $processor?->id === $p->id]) style="text-decoration:none">{{ $p->name }}</a>
            @endforeach
        </div>
    @endif

    <div class="grid g4">
        <x-kpi icon="file" :label="__('Plate bills')" :foot="$who ?: __('All processors')">Rs {{ number_format($billed) }}</x-kpi>
        <x-kpi icon="check" tone="good" :label="__('Paid on bills')">Rs {{ number_format($paid) }}</x-kpi>
        <x-kpi icon="wallet" tone="warn" :label="__('Advance with processor')" :foot="__('Given, not used on a bill yet')">Rs {{ number_format($advance) }}</x-kpi>
        <x-kpi icon="alert" :tone="$toPay > 0 ? 'bad' : 'good'" :label="__('Remaining to pay')" :foot="__('Bills less what was paid')">Rs {{ number_format($toPay) }}</x-kpi>
    </div>

    @can('plates.manage')
        <div class="grid g-half">
            {{-- Money given: clears the oldest bills first; anything more is kept as advance. --}}
            <form method="POST" action="{{ route('plate-jobs.payments.store') }}" class="card">
                @csrf
                <div class="card-h"><div><h3>{{ __('Pay processor / give advance') }}</h3><div class="sub">{{ __("Clears the oldest unpaid plate bills first; anything more is kept as advance for the next bills. Comes off the manager's cash.") }}</div></div></div>
                <div class="card-b"><div class="form">
                    <div @class(['field', 'has-err' => $errors->has('party_id')])>
                        <label for="f_pay_processor">{{ __('Processor') }} <span class="muted">*</span></label>
                        <input type="text" id="f_pay_processor" name="processor_name" value="{{ old('processor_name', $who) }}" list="payProcessors" autocomplete="off" maxlength="120" required>
                        <datalist id="payProcessors">@foreach ($processors as $p)<option value="{{ $p->name }}">@endforeach</datalist>
                        @error('party_id')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <x-field name="amount" type="number" min="1" step="1" :label="__('Amount (Rs)')" :value="$processor && $toPay > 0 ? $toPay : null" required />
                    <x-field name="paid_on" type="date" :label="__('Date')" :value="today()->toDateString()" :max="today()->toDateString()" required />
                    <x-field name="note" :label="__('Note')" />
                </div></div>
                <div class="form-actions"><button class="btn primary" type="submit"><x-icon name="wallet" />{{ __('Save payment') }}</button></div>
            </form>

            {{-- Plates sent back: pick the job, how many, and when. --}}
            <form method="POST" action="{{ route('plate-jobs.returns.store') }}" class="card">
                @csrf
                <div class="card-h"><div><h3>{{ __('Plates returned') }}</h3><div class="sub">{{ __('Plates the processor sent back.') }}</div></div></div>
                <div class="card-b">
                    @if ($held->isEmpty())
                        <div class="empty"><b>{{ __('No plates are out with a processor') }}</b></div>
                    @else
                        <div class="form">
                            {{-- One line per processor and plate size: the plates still out from all their jobs together. The return
                                 is taken off the oldest jobs first; the bill rows below stay separate. --}}
                            @php
                                $iso = fn ($t) => "\u{2066}".$t."\u{2069}";
                                $groups = $held->groupBy(fn ($j) => $j->party_id.'|'.mb_strtolower(trim((string) $j->plate_size)));
                            @endphp
                            <div @class(['field', 'full', 'has-err' => $errors->has('group')])>
                                <label for="f_return_group">{{ __('Processor and plate size') }} <span class="muted">*</span></label>
                                <select class="select" id="f_return_group" name="group" required>
                                    <option value="">{{ __('Pick the processor and size') }}</option>
                                    @foreach ($groups as $key => $jobsInGroup)
                                        @php
                                            $first = $jobsInGroup->first();
                                            $out = $jobsInGroup->sum(fn ($j) => $j->platesHeld());
                                            $label = collect([
                                                $iso($first->processor->name),
                                                __('Size').' '.$iso($first->plate_size ?: '—'),
                                                __(':n plates still out', ['n' => $iso(number_format($out))]),
                                            ])->join('  |  ');
                                        @endphp
                                        <option value="{{ $key }}" data-out="{{ $out }}" @selected(old('group') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('group')<span class="err">{{ $message }}</span>@enderror
                            </div>
                            <x-field name="returned_plates" type="number" min="1" step="1" :label="__('Plates returned')" required />
                            <x-field name="returned_on" type="date" :label="__('Date')" :value="today()->toDateString()" :max="today()->toDateString()" required />
                        </div>
                    @endif
                </div>
                @if ($held->isNotEmpty())<div class="form-actions"><button class="btn" type="submit"><x-icon name="check" />{{ __('Save return') }}</button></div>@endif
            </form>
        </div>
        <script>
            (() => {
                // Picking a processor and size fills in all the plates still out with them.
                const job = document.getElementById('f_return_group'), n = document.querySelector('[name=returned_plates]');
                job?.addEventListener('change', () => { const o = job.selectedOptions[0]; if (o?.dataset.out) { n.value = o.dataset.out; n.max = o.dataset.out; } });
            })();
        </script>
    @endcan

    <div class="card">
        <div class="toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:8px">
            <h3 style="margin:0">{{ __('Plate bills') }}</h3>
            <div class="tabs">
                @foreach (['unpaid' => __('Unpaid'), 'paid' => __('Paid'), 'all' => __('All')] as $k => $l)
                    <a href="{{ route('plate-jobs.index', array_filter(['show' => $k, 'processor' => $processor?->id])) }}" @class(['tab', 'on' => $show === $k]) style="text-decoration:none">{{ $l }}</a>
                @endforeach
            </div>
        </div>
        @include('plate-jobs._table', ['jobs' => $jobs, 'withProcessor' => ! $processor])
        {{ $jobs->links() }}
    </div>

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Money given') }}</h3><div class="sub">{{ __('Each payment, how much of it went on bills, and what is still kept as advance.') }}</div></div></div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Date') }}</th>@unless ($processor)<th>{{ __('Processor') }}</th>@endunless<th class="r">{{ __('Amount') }}</th><th class="r">{{ __('Used on bills') }}</th><th class="r">{{ __('Advance left') }}</th><th>{{ __('Note') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($payments as $pay)
                @php $used = (int) $pay->billPayments->sum('amount'); @endphp
                <tr data-href="{{ route('records.show', ['processor-payment', $pay->id]) }}">
                    <td><bdi dir="ltr">{{ $pay->paid_on->format('j M Y') }}</bdi></td>
                    @unless ($processor)<td><b>{{ $pay->processor?->name }}</b></td>@endunless
                    <td class="r num"><b>Rs {{ number_format($pay->amount) }}</b></td>
                    <td class="r num">Rs {{ number_format($used) }}</td>
                    <td class="r num">@if ($pay->amount - $used > 0)<b style="color:var(--warn, #b7791f)">Rs {{ number_format($pay->amount - $used) }}</b>@else — @endif</td>
                    <td>{{ $pay->note ?: '—' }}</td>
                    <td class="r">@can('plates.manage')<x-delete-button :action="route('records.destroy', ['processor-payment', $pay->id])" :confirm="__('Delete this payment of Rs :n? The bills it paid become unpaid again, and it leaves Cash-out.', ['n' => number_format($pay->amount)])" :label="''" small aria-label="{{ __('Delete') }}" />@endcan</td>
                </tr>
            @empty
                <tr><td colspan="{{ $processor ? 6 : 7 }}"><div class="empty"><b>{{ __('No money given yet') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</x-layouts.app>
