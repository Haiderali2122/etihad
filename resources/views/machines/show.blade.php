<x-layouts.app :title="$machine->code">
    <x-page-head :title="$machine->code.' '.$machine->name" :subtitle="$machine->type->label().($machine->model ? ' · '.$machine->model : '')">
        <x-slot:actions>
            <x-badge :tone="$machine->status->tone()">{{ $machine->status->label() }}</x-badge>
            @can('machines.manage')<a class="btn" href="{{ route('machines.edit', $machine) }}"><x-icon name="edit" />{{ __('Edit') }}</a>@endcan
            @can('machines.repair')<a class="btn primary" href="{{ route('machine-repairs.create', ['machine' => $machine->id]) }}"><x-icon name="wrench" />{{ __('Log repair') }}</a>@endcan
        </x-slot:actions>
    </x-page-head>
    @php $approved = $repairs->where('status', \App\Enums\ApprovalStatus::Approved); @endphp
    <div class="grid g4">
        <x-kpi icon="wrench" tone="bad" :label="__('Repairs paid')" :foot="trans_choice(':count bill|:count bills', $approved->count())">Rs {{ number_format($approved->sum('amount_paid')) }}</x-kpi>
        <x-kpi icon="down" tone="good" :label="__('Discounts won')">Rs {{ number_format($approved->sum('discount')) }}</x-kpi>
        <x-kpi icon="wallet" :label="__('Cash expenses')" :foot="__('From the roznamcha')">Rs {{ number_format($expenses->sum('amount')) }}</x-kpi>
        <x-kpi icon="box" :label="__('Purchase price')">{{ $machine->purchase_price ? 'Rs '.number_format($machine->purchase_price) : '—' }}</x-kpi>
    </div>
    <div class="card">
        <div class="card-h"><div><h3>{{ __('Repair log') }}</h3></div></div>
        <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Work') }}</th><th>{{ __('Vendor') }}</th><th class="r">{{ __('Bill') }}</th><th class="r">{{ __('Discount') }}</th><th class="r">{{ __('Paid') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($repairs as $r)
                <tr>
                    <td>{{ $r->repaired_on->format('j M Y') }}</td>
                    <td><div class="main">{{ $r->description }}</div><div class="meta">{{ $r->kind->label() }}{{ $r->part ? ' · '.$r->part->name : '' }}{{ $r->invoice_no ? ' · '.__('Invoice :no', ['no' => $r->invoice_no]) : '' }}</div></td>
                    <td>{{ $r->vendor->name }}</td>
                    <td class="r num">{{ number_format($r->bill_amount) }}</td>
                    <td class="r num">{{ $r->discount ? number_format($r->discount) : '—' }}</td>
                    <td class="r num"><b>Rs {{ number_format($r->amount_paid) }}</b></td>
                    <td><x-badge :tone="$r->status->tone()">{{ $r->status->label() }}</x-badge>@if ($r->review_note)<div class="meta">{{ $r->review_note }}</div>@endif</td>
                    <td class="r">@include('partials.attachment', ['path' => $r->attachment])</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><b>{{ __('No repairs logged') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>
    @if ($machine->notes)
        <div class="card"><div class="card-b">{{ $machine->notes }}</div></div>
    @endif
</x-layouts.app>
