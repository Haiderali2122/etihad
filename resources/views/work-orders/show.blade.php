@php
    use App\Enums\ContractorJobKind;
    use App\Enums\PrintMode;
    use App\Enums\RimMove;
    use App\Enums\WorkOrderStage;
    $next = $order->stage->next();
    $qty = fn ($v) => \App\Models\InventoryItem::qty($v);
    $stages = WorkOrderStage::cases();
    $at = array_search($order->stage, $stages, true);
    $blocker = $order->paper_issue_id && $next ? $order->advanceBlocker() : null;
    $outside = $order->mode === PrintMode::Outsource;
    $younus = $order->rimLoadings->sortBy('loaded_on')->values();
    // The "Move to …" button for steps that only need confirming once their work is recorded.
    $canMove = $order->paper_issue_id && $next && in_array($order->stage, [WorkOrderStage::ToFolding, WorkOrderStage::Folding, WorkOrderStage::Binding], true)
        || ($order->stage === WorkOrderStage::Printing && $outside && $order->paper_issue_id);
    $jobsOf = fn (ContractorJobKind $k) => $order->contractorJobs->where('kind', $k)->values();
@endphp
<x-layouts.app :title="$order->number">
    <x-page-head :title="$order->number.' · '.$order->bookName()" :subtitle="__(':copies copies · :forms forms · :where', ['copies' => number_format($order->copies), 'forms' => $qty($order->forms), 'where' => $order->printedAt()])">
        <x-slot:actions>
            <x-badge :tone="$order->status->value === 'rejected' ? 'bad' : $order->stage->tone()">{{ $order->status->value === 'rejected' ? __('Sent back') : $order->stage->label() }}</x-badge>
            <a class="btn" href="{{ route('work-orders.index') }}">{{ __('Back') }}</a>
            @can('orders.manage')
                @if ($order->canBeDeleted())
                    <x-delete-button :action="route('work-orders.destroy', $order)" :confirm="__('Delete :n? Its :r rims go back into stock.', ['n' => $order->number, 'r' => number_format($order->rims_needed)])" :label="__('Delete')" />
                @endif
            @endcan
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif
    @if ($order->review_note)<div class="alert"><x-icon name="alert" /><div><b>{{ __('Owner note') }}:</b> {{ $order->review_note }}</div></div>@endif

    {{-- Where the order is --}}
    <div class="card"><div class="card-b">
        <div class="stages">
            @foreach ($stages as $i => $s)
                <div @class(['stg', 'done' => $order->paper_issue_id && $i < $at, 'on' => $i === $at])><i>{{ $i + 1 }}</i><span>{{ $s->label() }}</span></div>
            @endforeach
        </div>
    </div></div>

    {{-- Only the current step: what to record now --}}
    <div class="card">
        <div class="card-h"><div><h3>{{ __('Now: :stage', ['stage' => $order->stage->label()]) }}</h3>@if ($blocker)<div class="sub">{{ $blocker }}</div>@endif</div>
            @can('orders.manage')
                @if ($canMove)
                    <form method="POST" action="{{ route('work-orders.advance', $order) }}">@csrf @method('PATCH')<button class="btn primary" type="submit" @disabled($blocker)><x-icon name="chev" />{{ __('Move to :stage', ['stage' => $next->label()]) }}</button></form>
                @endif
            @endcan
        </div>
        <div class="card-b">
            @switch($order->stage)
                @case(WorkOrderStage::Waiting)
                    <div class="meta"><b>{{ __('Waiting for the owner\'s approval.') }}</b> {{ __('Paper is issued from stock once it is approved.') }}</div>
                    @break

                @case(WorkOrderStage::Printing)
                    {{-- First move: Younus carries the paper from the godown to the press (and, for an outside press, takes the printing charge). --}}
                    @php $carried = $younus->whereIn('kind', [RimMove::ToPress, RimMove::ToOutside]); $rate = fn (RimMove $k) => ($r = \App\Models\RimLoading::lastRate($k)) !== null ? $qty($r) : null; @endphp
                    @if ($carried->isNotEmpty())
                        @foreach ($younus->whereIn('kind', [RimMove::ToPress, RimMove::ToOutside, RimMove::Printing]) as $l)
                            <div class="stat-line"><span>{{ __('Younus') }} · {{ $l->kind->label() }}</span><b>{{ $qty($l->rims) }} × {{ $qty($l->rate_per_rim) }} = Rs {{ number_format($l->amount) }}</b></div>
                        @endforeach
                    @else
                        @can('orders.manage')
                            {{-- Younus's work is entered on his khata; this opens it with the order filled in. --}}
                            <a class="btn" style="margin-bottom:14px" href="{{ route('rim-loadings.index', ['order' => $order->id, 'kind' => ($outside ? RimMove::ToOutside : RimMove::ToPress)->value]) }}"><x-icon name="truck" />{{ $outside ? __('Add Younus work: godown → outside press') : __('Add Younus work: godown → our press') }}</a>
                        @endcan
                    @endif

                    @if (! $outside)
                        @can('orders.manage')
                            <form method="POST" action="{{ route('work-orders.advance', $order) }}" class="form">
                                @csrf @method('PATCH')
                                <x-field name="printed_copies" type="number" min="1" :label="__('Printed copies')" :value="$order->copies" required />
                                <x-field name="printed_on" type="date" :label="__('Printing finished on')" :value="today()->toDateString()" required />
                                <div class="field full"><button class="btn primary" type="submit" style="justify-content:center"><x-icon name="chev" />{{ __('Printing done') }}</button></div>
                            </form>
                        @endcan
                    @else
                        @foreach ($order->outsideReceipts as $r)
                            <div class="stat-line"><span>{{ $r->received_on->format('j M Y') }}</span><b>{{ number_format($r->printed_copies) }} {{ __('copies') }}@if ($r->leftover_rims) · {{ $r->leftover_rims }} {{ __('rims back unused') }}@endif @can('orders.manage')<x-delete-button :action="route('records.destroy', ['outside-print-receipt', $r->id])" :label="''" small :danger="false" aria-label="{{ __('Delete') }}" />@endcan</b></div>
                        @endforeach
                        @can('orders.manage')
                            <form method="POST" action="{{ route('work-orders.outside-receipts.store', $order) }}" class="form" style="margin-top:8px">
                                @csrf
                                <x-field name="printed_copies" type="number" min="0" :label="__('Printed copies received from :press', ['press' => $order->press?->name])" required />
                                <x-field name="received_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                                <x-field name="leftover_rims" type="number" min="0" :label="__('Unused rims returned')" :hint="__('Added back to paper stock')" />
                                <x-field name="godown_id" :label="__('Unloaded in')" :options="$godowns->pluck('name', 'id')" :placeholder="__('Choose location')" />
                                <x-field name="note" :label="__('Note')" full />
                                <div class="field full"><button class="btn primary" type="submit" style="justify-content:center"><x-icon name="download" />{{ __('Record printed work received') }}</button></div>
                            </form>
                        @endcan
                    @endif
                    @break

                @case(WorkOrderStage::ToFolding)
                    {{-- Younus carries the printed rims to folding: from our press, or back from the outside press. --}}
                    @php $kind = $outside ? RimMove::FromOutside : RimMove::ToFolding; $loaded = $younus->where('kind', $kind); $last = \App\Models\RimLoading::lastRate($kind); @endphp
                    @forelse ($loaded as $l)
                        <div class="stat-line"><span>{{ __('Younus') }} · {{ $l->kind->label() }} · {{ $l->loaded_on->format('j M') }}</span><b>{{ $qty($l->rims) }} × {{ $qty($l->rate_per_rim) }} = Rs {{ number_format($l->amount) }}</b></div>
                    @empty
                        @can('orders.manage')
                            {{-- Entered on Younus ka khata; saving it there also finishes this step. --}}
                            <a class="btn primary" href="{{ route('rim-loadings.index', ['order' => $order->id, 'kind' => $kind->value]) }}"><x-icon name="truck" />{{ $outside ? __('Add Younus work: outside press → back for folding') : __('Add Younus work: our press → folding') }}</a>
                        @endcan
                    @endforelse
                    @break

                @case(WorkOrderStage::Folding)
                @case(WorkOrderStage::Binding)
                    @php $kind = $order->stage === WorkOrderStage::Folding ? ContractorJobKind::Folding : ContractorJobKind::Binding; @endphp
                    @forelse ($jobsOf($kind) as $job)
                        <div class="stat-line"><span><a href="{{ route('contractors.show', $job->contractor) }}">{{ $job->contractor->name }}</a> · {{ $job->job_on->format('j M') }} · Rs {{ number_format($job->amount) }}</span>
                            <b>@if ($job->isDone())<x-badge tone="good">{{ __('Done :date', ['date' => $job->done_on?->format('j M')]) }}</x-badge>@else @can('contractors.manage')<form method="POST" action="{{ route('contractor-jobs.done', $job) }}" class="filters" style="flex-wrap:nowrap">@csrf @method('PATCH')<input class="select" type="date" name="done_on" value="{{ today()->toDateString() }}" min="{{ $job->job_on->toDateString() }}" max="{{ today()->toDateString() }}" required aria-label="{{ __('Finished on') }}"><button class="btn sm" type="submit"><x-icon name="check" />{{ __('Done') }}</button></form>@else<x-badge tone="info">{{ __('In progress') }}</x-badge>@endcan @endif</b></div>
                    @empty
                        <div class="meta">{{ __('Give the :work job to a contractor.', ['work' => mb_strtolower($kind->label())]) }}</div>
                    @endforelse
                    @can('contractors.manage')<div style="margin-top:10px"><a class="btn" href="{{ route('contractor-jobs.create', ['order' => $order->id, 'kind' => $kind->value]) }}"><x-icon name="plus" />{{ __('Give :work job', ['work' => mb_strtolower($kind->label())]) }}</a></div>@endcan
                    @break

                @case(WorkOrderStage::Ready)
                    <div class="meta">{{ __(':done of :all copies loaded so far.', ['done' => number_format($dispatched), 'all' => number_format($order->copies)]) }}</div>
                    @can('dispatch.manage')@if ($order->canBeLoaded())<div style="margin-top:10px"><a class="btn primary" href="{{ route('dispatches.create', ['order' => $order->id]) }}"><x-icon name="truck" />{{ __('Load on truck') }}</a></div>@endif @endcan
                    @break

                @default
                    <div class="meta">{{ __('All :n copies are loaded and dispatched.', ['n' => number_format($order->copies)]) }}</div>
            @endswitch
        </div>
    </div>

    <div class="grid g4">
        <x-kpi icon="paper" :label="__('Paper')" :foot="$order->paperType->label().' · '.$order->party->name">{{ number_format($order->rims_needed) }}<small>{{ __('rims') }}</small></x-kpi>
        <x-kpi icon="truck" :label="__('Younus ka khata')" :foot="__('Rs :n unpaid', ['n' => number_format($younus->whereNull('paid_on')->sum('amount'))])">Rs {{ number_format($younus->sum('amount')) }}</x-kpi>
        <x-kpi icon="fold" :label="__('Folding & binding')" :foot="trans_choice(':count job|:count jobs', $order->contractorJobs->count())">Rs {{ number_format($order->contractorJobs->sum('amount')) }}</x-kpi>
        <x-kpi icon="truck" tone="good" :label="__('Dispatched')" :foot="__('of :n copies', ['n' => number_format($order->copies)])">{{ number_format($dispatched) }}</x-kpi>
    </div>

    {{-- Everything else, folded away: open a line to see it --}}
    <details class="card">
        <summary class="card-h" style="cursor:pointer"><div><h3>{{ __('Order details') }}</h3><div class="sub">{{ __('Ordered :date', ['date' => $order->ordered_on->format('j M Y')]) }}@if ($order->due_on) · {{ __('due :date', ['date' => $order->due_on->format('j M Y')]) }}@endif · {{ $order->buyer?->name ?? __('no buyer set') }}</div></div></summary>
        <div class="card-b">
            <div class="stat-line"><span>{{ __('Printed at') }}</span><b>{{ $order->printedAt() }}@if ($outside && $order->printing_rate !== null) · {{ __('Rs :r per rim', ['r' => $qty($order->printing_rate)]) }}@endif</b></div>
            <div class="stat-line"><span>{{ __('Wastage allowed') }}</span><b>{{ $qty($order->wastage_percent) }}% · {{ number_format($rims['needed'] - $rims['base']) }} {{ __('rims') }}</b></div>
            @if ($order->printed_on)<div class="stat-line"><span>{{ __('Printed') }}</span><b>{{ $order->printed_on->format('j M Y') }} · {{ number_format($order->printed_copies) }} {{ __('copies') }}</b></div>@endif
            <div class="stat-line"><span>{{ __('Paper issued') }}</span><b>{{ $order->paperIssue ? $order->paperIssue->issued_on->format('j M Y').' · '.number_format($order->paperIssue->quantity).' '.__('rims') : __('Not yet') }}</b></div>
            <div class="stat-line"><span>{{ __('Entered by') }}</span><b>{{ $order->creator?->name ?? '—' }}</b></div>
            @if ($order->note)<div class="stat-line"><span>{{ __('Note') }}</span><b>{{ $order->note }}</b></div>@endif
        </div>
    </details>

    <details class="card">
        <summary class="card-h" style="cursor:pointer"><div><h3>{{ __('Younus ka khata for this order') }}</h3><div class="sub">{{ trans_choice(':count line|:count lines', $younus->count()) }} · Rs {{ number_format($younus->sum('amount')) }} · {{ __('Rs :n unpaid', ['n' => number_format($younus->whereNull('paid_on')->sum('amount'))]) }}</div></div><a class="btn sm" href="{{ route('rim-loadings.index', ['show' => 'all']) }}">{{ __('Open khata') }}</a></summary>
        <div class="card-b">
            @forelse ($younus as $l)
                <div class="stat-line"><span>{{ $l->loaded_on->format('j M') }} · {{ $l->kind->label() }}</span><b>{{ $qty($l->rims) }} × {{ $qty($l->rate_per_rim) }} = Rs {{ number_format($l->amount) }} @if ($l->isPaid())<x-badge tone="good">{{ __('Paid') }}</x-badge>@else<x-badge tone="warn">{{ __('Unpaid') }}</x-badge>@endif</b></div>
            @empty
                <div class="empty">{{ __('No lines yet') }}</div>
            @endforelse
        </div>
    </details>

    @if ($outside)
        <details class="card">
            <summary class="card-h" style="cursor:pointer"><div><h3>{{ __('Outside printing') }}</h3><div class="sub">{{ __(':n copies received back · :r rims returned', ['n' => number_format($printedBack), 'r' => number_format($order->outsideReceipts->sum('leftover_rims'))]) }}</div></div></summary>
            <div class="card-b">
                @forelse ($order->outsideReceipts as $r)
                    <div class="stat-line"><span>{{ $r->received_on->format('j M Y') }}</span><b>{{ number_format($r->printed_copies) }} {{ __('copies') }}@if ($r->leftover_rims) · {{ $r->leftover_rims }} {{ __('rims back') }}@endif</b></div>
                @empty
                    <div class="empty">{{ __('Nothing received back yet') }}</div>
                @endforelse
            </div>
        </details>
    @else
        <details class="card">
            <summary class="card-h" style="cursor:pointer"><div><h3>{{ __('Plates') }}</h3><div class="sub">{{ trans_choice(':count plate job|:count plate jobs', $order->plateJobs->count()) }} · Rs {{ number_format($order->plateJobs->sum('amount')) }}</div></div>@can('plates.manage')<a class="btn sm" href="{{ route('plate-jobs.create', ['order' => $order->id]) }}"><x-icon name="plus" />{{ __('Add') }}</a>@endcan</summary>
            <div class="card-b">
                @forelse ($order->plateJobs as $p)
                    <div class="stat-line"><span>{{ $p->job_on->format('j M') }} · {{ $p->processor->name }}</span><b>{{ $p->plates }} {{ __('plates') }} · Rs {{ number_format($p->amount) }} @if ($p->isPaid())<x-badge tone="good">{{ __('Paid') }}</x-badge>@else<x-badge tone="warn">{{ __('Unpaid') }}</x-badge>@endif</b></div>
                @empty
                    <div class="empty">{{ __('No plates recorded') }}</div>
                @endforelse
            </div>
        </details>
    @endif

    <details class="card">
        <summary class="card-h" style="cursor:pointer"><div><h3>{{ __('Folding & binding') }}</h3><div class="sub">{{ trans_choice(':count job|:count jobs', $order->contractorJobs->count()) }} · Rs {{ number_format($order->contractorJobs->sum('amount')) }}</div></div></summary>
        <div class="card-b">
            @forelse ($order->contractorJobs as $job)
                <div class="stat-line"><span>{{ $job->kind->label() }} · <a href="{{ route('contractors.show', $job->contractor) }}">{{ $job->contractor->name }}</a> · {{ $job->job_on->format('j M') }}</span><b>Rs {{ number_format($job->amount) }} <x-badge :tone="$job->isDone() ? 'good' : 'info'">{{ $job->isDone() ? __('Done') : __('In progress') }}</x-badge></b></div>
            @empty
                <div class="empty">{{ __('No folding or binding given out yet') }}</div>
            @endforelse
        </div>
    </details>

    <details class="card">
        <summary class="card-h" style="cursor:pointer"><div><h3>{{ __('Truck loading') }}</h3><div class="sub">{{ __(':done of :all copies loaded', ['done' => number_format($dispatched), 'all' => number_format($order->copies)]) }}</div></div>@can('dispatch.manage')@if ($order->canBeLoaded())<a class="btn sm primary" href="{{ route('dispatches.create', ['order' => $order->id]) }}"><x-icon name="truck" />{{ __('Load') }}</a>@endif @endcan</summary>
        <div class="card-b">
            @forelse ($order->dispatches as $d)
                <a class="stat-line" href="{{ route('dispatches.show', $d) }}" style="color:inherit;text-decoration:none"><span>{{ $d->number }} · {{ $d->dispatched_on->format('j M') }} · {{ $d->buyer->name }}</span><b>{{ number_format($d->copies) }} {{ __('copies') }}</b></a>
            @empty
                <div class="empty">{{ __('Nothing loaded yet') }}</div>
            @endforelse
        </div>
    </details>

    <details class="card">
        <summary class="card-h" style="cursor:pointer"><div><h3>{{ __('Waste') }}</h3><div class="sub">{{ __('Allowed: :n rims', ['n' => number_format($rims['needed'] - $rims['base'])]) }} · {{ trans_choice(':count entry|:count entries', $order->wasteEntries->count()) }}</div></div>@can('waste.manage')@if ($order->paper_issue_id)<a class="btn sm" href="{{ route('waste.create', ['order' => $order->id]) }}"><x-icon name="plus" />{{ __('Record') }}</a>@endif @endcan</summary>
        <div class="card-b">
            @forelse ($order->wasteEntries as $w)
                <div class="stat-line"><span>{{ $w->recorded_on->format('j M') }} · {{ $w->stage->label() }}</span><b>{{ $qty($w->quantity) }} {{ $w->unit }} · {{ $w->disposal->label() }}</b></div>
            @empty
                <div class="empty">{{ __('No waste recorded') }}</div>
            @endforelse
        </div>
    </details>
    @push('scripts')
        <script>
            // Each Younus box shows what will be owed to him as rims and rates are typed.
            document.querySelectorAll('form[data-younus]').forEach(form => {
                const val = sel => +(form.querySelector(sel)?.value || 0), fmt = n => 'Rs ' + Math.round(n).toLocaleString('en-PK');
                const set = (sel, n) => { const el = form.querySelector(sel); if (el) { el.textContent = fmt(n); } };
                const calc = () => {
                    const rims = val('[data-rims]'), labour = rims * val('[data-rate]');
                    const printRate = form.querySelector('[data-print-rate]') ? val('[data-print-rate]') : +(form.dataset.printingRate || 0);
                    const printing = form.dataset.printingRate !== undefined || form.querySelector('[data-print-rate]') ? rims * printRate : 0;
                    set('[data-labour]', labour); set('[data-printing]', printing); set('[data-total]', labour + printing);
                };
                form.addEventListener('input', calc);
                calc();
            });
        </script>
    @endpush
</x-layouts.app>
