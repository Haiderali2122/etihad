<x-layouts.app :title="__('Machines')">
    <x-page-head :title="__('Machines')" :subtitle="__('Printing and binding machines with their repairs and part bills.')">
        <x-slot:actions>
            @can('machines.manage')<a class="btn" href="{{ route('machines.create') }}"><x-icon name="plus" />{{ __('Add machine') }}</a>@endcan
            @can('machines.repair')<a class="btn primary" href="{{ route('machine-repairs.create') }}"><x-icon name="wrench" />{{ __('Log repair') }}</a>@endcan
        </x-slot:actions>
    </x-page-head>
    <div class="grid g3">
        @forelse ($machines as $machine)
            @php $last = $machine->repairs->first(); @endphp
            <a class="card mach" href="{{ route('machines.show', $machine) }}" style="color:inherit;text-decoration:none">
                <div class="mach-h"><div><div class="mach-no">{{ $machine->code }}</div><div class="mach-name">{{ $machine->name }}</div></div><x-badge :tone="$machine->status->tone()">{{ $machine->status->label() }}</x-badge></div>
                <dl>
                    <dt>{{ __('Type') }}</dt><dd>{{ $machine->type->label() }}</dd>
                    <dt>{{ __('Model') }}</dt><dd>{{ $machine->model ?: '—' }}</dd>
                    <dt>{{ __('Last repair') }}</dt><dd>{{ $last ? $last->description.', '.$last->repaired_on->format('j M') : '—' }}</dd>
                    <dt>{{ __('Spend this month') }}</dt><dd class="num">Rs {{ number_format($machine->month_spend ?? 0) }}</dd>
                    <dt>{{ __('Spend all time') }}</dt><dd class="num">Rs {{ number_format($machine->total_spend ?? 0) }}</dd>
                </dl>
            </a>
        @empty
            <div class="card"><div class="empty"><b>{{ __('No machines yet') }}</b></div></div>
        @endforelse
    </div>
</x-layouts.app>
