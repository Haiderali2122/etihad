<x-layouts.app :title="__('Expense heads')">
    <x-page-head :title="__('Expense heads')" :subtitle="__('The heads the manager chooses from when logging a spend: petrol, diesel, machine repair and so on.')">
        <x-slot:actions>
            <a class="btn" href="{{ route('expenses.index') }}">{{ __('Back to cash-out') }}</a>
            <a class="btn primary" href="{{ route('expense-heads.create') }}"><x-icon name="plus" />{{ __('Add expense head') }}</a>
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="card"><div class="tbl-wrap"><table>
        <thead><tr><th>{{ __('Head') }}</th><th>{{ __('Name in Urdu') }}</th><th class="r">{{ __('Entries') }}</th><th class="r">{{ __('This month') }}</th><th class="r">{{ __('All time') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($heads as $head)
            <tr>
                <td><span class="who"><i style="width:14px;height:14px;border-radius:4px;background:{{ $head->color }};display:inline-block"></i><span class="main">{{ $head->name }}</span></span></td>
                <td><span style="font-family:var(--urdu)" dir="rtl">{{ $head->name_ur ?: '—' }}</span></td>
                <td class="r num"><a href="{{ route('expenses.index', ['head' => $head->id]) }}">{{ number_format($head->expenses_count) }}</a></td>
                <td class="r num">Rs {{ number_format($head->month_amount ?? 0) }}</td>
                <td class="r num"><b>Rs {{ number_format($head->expenses_sum_amount ?? 0) }}</b></td>
                <td><x-badge :tone="$head->is_active ? 'good' : 'mute'">{{ $head->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                <td class="r" style="white-space:nowrap">
                    <a class="btn sm" href="{{ route('expense-heads.edit', $head) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                    @if ($head->expenses_count === 0)
                        <form method="POST" action="{{ route('expense-heads.destroy', $head) }}" style="display:inline" onsubmit="return confirm(@js(__('Delete the :name head?', ['name' => $head->name])))">
                            @csrf @method('DELETE')
                            <button class="btn sm" type="submit" style="color:var(--bad)" aria-label="{{ __('Delete :name', ['name' => $head->name]) }}"><x-icon name="x" /></button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty"><b>{{ __('No expense heads yet') }}</b></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.app>
