<x-layouts.app :title="__('Payroll')">
    <x-page-head :title="__('Payroll')" :subtitle="__('One payroll per month, worked out from attendance, overtime, advances and loan instalments.')" />

    @can('payroll.prepare')
        <form method="POST" action="{{ route('payroll.store') }}" class="card">
            @csrf
            <div class="toolbar">
                <div class="filters" style="align-items:center">
                    <label for="month" class="muted">{{ __('Prepare payroll for') }}</label>
                    <input class="select" id="month" type="month" name="month" value="{{ $suggested }}" max="{{ now()->format('Y-m') }}" required>
                </div>
                <button class="btn primary" type="submit"><x-icon name="cal" />{{ __('Prepare payroll') }}</button>
            </div>
        </form>
    @endcan

    <div class="card"><div class="tbl-wrap"><table>
        <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Pay date') }}</th><th class="r">{{ __('Staff') }}</th><th class="r">{{ __('Net pay') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($runs as $run)
            <tr>
                <td><a class="main" href="{{ route('payroll.show', $run) }}" style="color:inherit;text-decoration:none">{{ $run->label() }}</a></td>
                <td>{{ $run->pay_date?->format('j M Y') ?? '—' }}</td>
                <td class="r num">{{ $run->lines_count }}</td>
                <td class="r num"><b>Rs {{ number_format($run->lines_sum_net_pay ?? 0) }}</b></td>
                <td><x-badge :tone="$run->status->tone()">{{ $run->status->label() }}</x-badge></td>
                <td class="r"><a class="btn sm" href="{{ route('payroll.show', $run) }}">{{ __('Open') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty"><b>{{ __('No payroll yet') }}</b>{{ __('Pick a month above to prepare the first one.') }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.app>
