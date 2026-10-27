@php $balance = $earned - $paid; @endphp
<x-layouts.app :title="$contractor->name">
    <x-page-head :title="$contractor->localName()" :subtitle="$contractor->work->label().($contractor->phone ? ' · '.$contractor->phone : '')">
        <x-slot:actions>
            <a class="btn" href="{{ route('contractors.index') }}">{{ __('Back') }}</a>
            @can('contractors.manage')
                <a class="btn" href="{{ route('contractors.edit', $contractor) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                @if ($jobs->isEmpty() && $payments->isEmpty())
                    <form method="POST" action="{{ route('contractors.destroy', $contractor) }}" onsubmit="return confirm(@js(__('Delete :name?', ['name' => $contractor->name])))">@csrf @method('DELETE')<button class="btn" type="submit" style="color:var(--bad)"><x-icon name="x" />{{ __('Delete') }}</button></form>
                @endif
                <a class="btn primary" href="{{ route('contractor-jobs.create', ['contractor' => $contractor->id]) }}"><x-icon name="fold" />{{ __('Give job') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="grid g3">
        <x-kpi icon="fold" :label="__('Earned on jobs')">Rs {{ number_format($earned) }}</x-kpi>
        <x-kpi icon="wallet" :label="__('Advances and payments')" :foot="__('Approved and waiting')">Rs {{ number_format($paid) }}</x-kpi>
        <x-kpi icon="check" :tone="$balance > 0 ? 'bad' : 'good'" :label="$balance >= 0 ? __('Still to pay') : __('Paid ahead')">Rs {{ number_format(abs($balance)) }}</x-kpi>
    </div>

    <div class="grid g-main">
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Hisab per order') }}</h3></div></div>
            <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Order') }}</th><th class="r">{{ __('Earned') }}</th><th class="r">{{ __('Paid against it') }}</th><th class="r">{{ __('Balance') }}</th></tr></thead>
                <tbody>
                @forelse ($byOrder as $row)
                    <tr>
                        <td>@if ($row['order'])<a href="{{ route('work-orders.show', $row['order']) }}">{{ $row['order']->number }}</a><div class="meta">{{ $row['order']->bookFormat->book->name }}</div>@else{{ __('Not for an order') }}@endif</td>
                        <td class="r num">Rs {{ number_format($row['earned']) }}</td>
                        <td class="r num">Rs {{ number_format($row['paid']) }}</td>
                        <td class="r num"><b>Rs {{ number_format($row['earned'] - $row['paid']) }}</b></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="empty">{{ __('No jobs yet') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div></div>
        </div>

        @can('contractors.manage')
            <form method="POST" action="{{ route('contractors.payments.store', $contractor) }}" class="card">
                @csrf
                <div class="card-h"><div><h3>{{ __('Pay contractor') }}</h3><div class="sub">{{ __('Advances and payments go to the owner for approval') }}</div></div></div>
                <div class="card-b"><div class="form" style="grid-template-columns:minmax(0,1fr)">
                    <x-field name="kind" :label="__('Type')" :options="collect(\App\Enums\ContractorPaymentKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])" required />
                    <x-field name="amount" type="number" min="1" :label="__('Amount (Rs)')" :value="$balance > 0 ? $balance : null" required />
                    <x-field name="paid_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                    <x-field name="work_order_id" :label="__('Against order (optional)')" :options="$orders->pluck('number', 'id')" :placeholder="__('Not for an order')" />
                    <x-field name="note" :label="__('Note')" />
                    <button class="btn primary" type="submit" style="justify-content:center"><x-icon name="wallet" />{{ __('Record') }}</button>
                </div></div>
            </form>
        @endcan
    </div>

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Jobs') }}</h3></div></div>
        <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Order') }}</th><th>{{ __('Work') }}</th><th>{{ __('Formula') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($jobs as $job)
                <tr>
                    <td>{{ $job->job_on->format('j M Y') }}</td>
                    <td>{{ $job->workOrder?->number ?? '—' }}</td>
                    <td>{{ $job->kind->label() }}</td>
                    <td class="meta" dir="ltr">{{ $job->formula() }}</td>
                    <td class="r num"><b>Rs {{ number_format($job->amount) }}</b></td>
                    <td><x-badge :tone="$job->isDone() ? 'good' : 'info'">{{ $job->isDone() ? __('Done') : __('In progress') }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty">{{ __('No jobs yet') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Advances and payments') }}</h3></div></div>
        <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Order') }}</th><th>{{ __('Note') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($payments as $p)
                <tr>
                    <td>{{ $p->paid_on->format('j M Y') }}</td><td>{{ $p->kind->label() }}</td><td>{{ $p->workOrder?->number ?? '—' }}</td><td>{{ $p->note ?: '—' }}</td>
                    <td class="r num"><b>Rs {{ number_format($p->amount) }}</b></td>
                    <td><x-badge :tone="$p->status->tone()">{{ $p->status->label() }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty">{{ __('Nothing paid yet') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>
</x-layouts.app>
