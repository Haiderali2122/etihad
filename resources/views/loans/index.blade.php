<x-layouts.app :title="__('Advances & loans')">
    <x-page-head :title="__('Advances & loans')" :subtitle="__('Staff loans repaid in monthly instalments. Payroll takes the usual instalment; it can be changed or skipped for any month.')">
        <x-slot:actions>@include('advances.actions')</x-slot:actions>
    </x-page-head>

    <div class="grid g3">
        <x-kpi icon="users" tone="bad" :label="__('Owed on loans')">Rs {{ number_format($outstanding) }}</x-kpi>
        <x-kpi icon="cal" :label="__('Instalments due each month')">Rs {{ number_format($monthlyDue) }}</x-kpi>
        <x-kpi icon="check" tone="good" :label="__('Open loans')">{{ $show === 'open' ? $loans->count() : '—' }}</x-kpi>
    </div>

    <div class="card">
        <div class="toolbar">
            @include('advances.tabs')
            <form class="filters" method="GET">
                <select class="select" name="show" aria-label="{{ __('Show') }}" onchange="this.form.submit()">
                    @foreach (['open' => __('Still owed'), 'cleared' => __('Cleared'), 'all' => __('All')] as $k => $l)<option value="{{ $k }}" @selected($show === $k)>{{ $l }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Issued') }}</th><th class="r">{{ __('Loan') }}</th><th class="r">{{ __('Instalment') }}</th><th class="r">{{ __('Repaid') }}</th><th class="r">{{ __('Balance') }}</th><th style="min-width:120px">{{ __('Progress') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($loans as $l)
                <tr>
                    <td><a class="main" href="{{ route('loans.show', $l) }}" style="color:inherit;text-decoration:none">{{ $l->employee->localName() }}</a><div class="meta">{{ $l->employee->code }}</div></td>
                    <td>{{ $l->issued_on->format('j M Y') }}<div class="meta">{{ __('from :month', ['month' => $l->first_instalment_month->translatedFormat('M Y')]) }}</div></td>
                    <td class="r num">Rs {{ number_format($l->amount) }}</td>
                    <td class="r num">Rs {{ number_format($l->monthly_instalment) }}</td>
                    <td class="r num">Rs {{ number_format($l->repaid()) }}</td>
                    <td class="r num"><b>Rs {{ number_format($l->balance()) }}</b></td>
                    <td><div class="meter"><i style="width:{{ max(2, $l->repaid() / max(1, $l->amount) * 100) }}%"></i></div></td>
                    <td>@if ($l->isCleared())<x-badge tone="good">{{ __('Cleared') }}</x-badge>@else<x-badge :tone="$l->status->tone()">{{ $l->status->label() }}</x-badge>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><b>{{ __('No loans here') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</x-layouts.app>
