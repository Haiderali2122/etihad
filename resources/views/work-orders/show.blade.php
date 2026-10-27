@php
    use App\Enums\WorkOrderStage;
    $next = $order->stage->next();
    $qty = fn ($v) => \App\Models\InventoryItem::qty($v);
    $stages = WorkOrderStage::cases();
    $at = array_search($order->stage, $stages, true);
@endphp
<x-layouts.app :title="$order->number">
    <x-page-head :title="$order->number.' · '.$order->bookName()" :subtitle="__(':copies copies · :forms forms · :where', ['copies' => number_format($order->copies), 'forms' => $qty($order->forms), 'where' => $order->printedAt()])">
        <x-slot:actions>
            <x-badge :tone="$order->status->value === 'rejected' ? 'bad' : $order->stage->tone()">{{ $order->status->value === 'rejected' ? __('Sent back') : $order->stage->label() }}</x-badge>
            <a class="btn" href="{{ route('work-orders.index') }}">{{ __('Back') }}</a>
            @can('orders.manage')
                @if (! $order->paper_issue_id)
                    <form method="POST" action="{{ route('work-orders.destroy', $order) }}" onsubmit="return confirm(@js(__('Delete :n?', ['n' => $order->number])))">@csrf @method('DELETE')<button class="btn" type="submit" style="color:var(--bad)"><x-icon name="x" />{{ __('Delete') }}</button></form>
                @endif
                @if ($next && $order->paper_issue_id)
                    <form method="POST" action="{{ route('work-orders.advance', $order) }}">@csrf @method('PATCH')<button class="btn primary" type="submit"><x-icon name="chev" />{{ __('Move to :stage', ['stage' => $next->label()]) }}</button></form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif
    @if ($order->isPending())<div class="alert"><x-icon name="clock" /><div><b>{{ __('Waiting for the owner\'s approval.') }}</b> {{ __('Paper is issued from stock once it is approved.') }}</div></div>@endif
    @if ($order->review_note)<div class="alert"><x-icon name="alert" /><div><b>{{ __('Owner note') }}:</b> {{ $order->review_note }}</div></div>@endif

    <div class="card"><div class="card-b">
        <div class="stages">
            @foreach ($stages as $i => $s)
                <div @class(['stg', 'done' => $order->paper_issue_id && $i < $at, 'on' => $i === $at])><i>{{ $i + 1 }}</i><span>{{ $s->label() }}</span></div>
            @endforeach
        </div>
    </div></div>

    <div class="grid g4">
        <x-kpi icon="paper" :label="__('Paper')" :foot="$order->paperType->label().' · '.$order->party->name">{{ number_format($order->rims_needed) }}<small>{{ __('rims') }}</small></x-kpi>
        <x-kpi icon="drop" tone="warn" :label="__('Wastage allowed')" :foot="__(':p% on :base rims', ['p' => $qty($order->wastage_percent), 'base' => number_format($rims['base'])])">{{ number_format($rims['needed'] - $rims['base']) }}<small>{{ __('rims') }}</small></x-kpi>
        <x-kpi icon="fold" :label="__('Contractor work')" :foot="trans_choice(':count job|:count jobs', $order->contractorJobs->count())">Rs {{ number_format($order->contractorJobs->sum('amount')) }}</x-kpi>
        <x-kpi icon="truck" tone="good" :label="__('Dispatched')" :foot="__('of :n copies', ['n' => number_format($order->copies)])">{{ number_format($dispatched) }}</x-kpi>
    </div>

    <div class="grid g-half">
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Order') }}</h3></div></div>
            <div class="card-b">
                <div class="stat-line"><span>{{ __('Ordered') }}</span><b>{{ $order->ordered_on->format('j M Y') }}</b></div>
                <div class="stat-line"><span>{{ __('Due') }}</span><b>{{ $order->due_on?->format('j M Y') ?? '—' }}</b></div>
                <div class="stat-line"><span>{{ __('Buyer') }}</span><b>{{ $order->buyer?->name ?? '—' }}</b></div>
                <div class="stat-line"><span>{{ __('Paper issued') }}</span><b>{{ $order->paperIssue ? $order->paperIssue->issued_on->format('j M Y').' · '.number_format($order->paperIssue->quantity).' '.__('rims') : __('Not yet') }}</b></div>
                <div class="stat-line"><span>{{ __('Entered by') }}</span><b>{{ $order->creator?->name ?? '—' }}</b></div>
                @if ($order->note)<div class="stat-line"><span>{{ __('Note') }}</span><b>{{ $order->note }}</b></div>@endif
            </div>
        </div>

        @if ($order->mode->value === 'outsource')
            <div class="card">
                <div class="card-h"><div><h3>{{ __('Outside printing') }}</h3><div class="sub">{{ __(':n copies received back · :r rims returned', ['n' => number_format($printedBack), 'r' => number_format($order->outsideReceipts->sum('leftover_rims'))]) }}</div></div></div>
                <div class="card-b">
                    @foreach ($order->outsideReceipts as $r)
                        <div class="stat-line"><span>{{ $r->received_on->format('j M Y') }}</span><b>{{ number_format($r->printed_copies) }} {{ __('copies') }}@if ($r->leftover_rims) · {{ $r->leftover_rims }} {{ __('rims back') }}@endif @if ($r->bill_amount) · Rs {{ number_format($r->bill_amount) }}@endif</b></div>
                    @endforeach
                    @can('orders.manage')
                        @if ($order->paper_issue_id)
                            <form method="POST" action="{{ route('work-orders.outside-receipts.store', $order) }}" class="form" style="margin-top:12px">
                                @csrf
                                <x-field name="printed_copies" type="number" min="0" :label="__('Printed copies received')" required />
                                <x-field name="received_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                                <x-field name="leftover_rims" type="number" min="0" :label="__('Unused rims returned')" :hint="__('Added back to paper stock')" />
                                <x-field name="godown_id" :label="__('Unloaded in')" :options="$godowns->pluck('name', 'id')" :placeholder="__('Choose location')" />
                                <x-field name="bill_amount" type="number" min="0" :label="__('Press bill (Rs)')" />
                                <x-field name="note" :label="__('Note')" />
                                <div class="field full"><button class="btn primary" type="submit" style="justify-content:center"><x-icon name="download" />{{ __('Record receipt') }}</button></div>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-h"><div><h3>{{ __('Plates') }}</h3></div>@can('plates.manage')<a class="btn sm" href="{{ route('plate-jobs.create', ['order' => $order->id]) }}"><x-icon name="plus" />{{ __('Add') }}</a>@endcan</div>
                <div class="card-b">
                    @forelse ($order->plateJobs as $p)
                        <div class="stat-line"><span>{{ $p->job_on->format('j M') }} · {{ $p->processor->name }}</span><b>{{ $p->plates }} {{ __('plates') }} · Rs {{ number_format($p->amount) }} @if ($p->isPaid())<x-badge tone="good">{{ __('Paid') }}</x-badge>@else<x-badge tone="warn">{{ __('Unpaid') }}</x-badge>@endif</b></div>
                    @empty
                        <div class="empty">{{ __('No plates recorded') }}</div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Folding & binding') }}</h3></div>
            @can('contractors.manage')@if ($order->paper_issue_id)<a class="btn sm" href="{{ route('contractor-jobs.create', ['order' => $order->id]) }}"><x-icon name="plus" />{{ __('Give job') }}</a>@endif @endcan</div>
        <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Contractor') }}</th><th>{{ __('Work') }}</th><th>{{ __('Date') }}</th><th>{{ __('Formula') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($order->contractorJobs as $job)
                <tr>
                    <td><a href="{{ route('contractors.show', $job->contractor) }}">{{ $job->contractor->name }}</a></td>
                    <td>{{ $job->kind->label() }}</td>
                    <td>{{ $job->job_on->format('j M Y') }}</td>
                    <td class="meta" dir="ltr">{{ $job->formula() }}</td>
                    <td class="r num"><b>Rs {{ number_format($job->amount) }}</b></td>
                    <td><x-badge :tone="$job->isDone() ? 'good' : 'info'">{{ $job->isDone() ? __('Done') : __('In progress') }}</x-badge></td>
                    <td class="r">@can('contractors.manage')@unless ($job->isDone())<form method="POST" action="{{ route('contractor-jobs.done', $job) }}">@csrf @method('PATCH')<button class="btn sm" type="submit"><x-icon name="check" />{{ __('Done') }}</button></form>@endunless @endcan</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty">{{ __('No folding or binding given out yet') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>

    <div class="grid g-half">
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Dispatch') }}</h3></div>@can('dispatch.manage')@if ($order->paper_issue_id)<a class="btn sm" href="{{ route('dispatches.create', ['order' => $order->id]) }}"><x-icon name="truck" />{{ __('Load') }}</a>@endif @endcan</div>
            <div class="card-b">
                @forelse ($order->dispatches as $d)
                    <a class="stat-line" href="{{ route('dispatches.show', $d) }}" style="color:inherit;text-decoration:none"><span>{{ $d->number }} · {{ $d->dispatched_on->format('j M') }} · {{ $d->buyer->name }}</span><b>{{ number_format($d->copies) }} {{ __('copies') }}</b></a>
                @empty
                    <div class="empty">{{ __('Nothing loaded yet') }}</div>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Waste') }}</h3><div class="sub">{{ __('Allowed: :n rims', ['n' => number_format($rims['needed'] - $rims['base'])]) }}</div></div>@can('waste.manage')@if ($order->paper_issue_id)<a class="btn sm" href="{{ route('waste.create', ['order' => $order->id]) }}"><x-icon name="plus" />{{ __('Record') }}</a>@endif @endcan</div>
            <div class="card-b">
                @forelse ($order->wasteEntries as $w)
                    <div class="stat-line"><span>{{ $w->recorded_on->format('j M') }} · {{ $w->stage->label() }}</span><b>{{ $qty($w->quantity) }} {{ $w->unit }} · {{ $w->disposal->label() }}</b></div>
                @empty
                    <div class="empty">{{ __('No waste recorded') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
