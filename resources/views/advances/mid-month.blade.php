<x-layouts.app :title="__('Mid-month advance')">
    <x-page-head :title="__('Mid-month advance')" :subtitle="__('Pre-filled at :p% of salary. Change any amount, or clear it for people who do not want the advance.', ['p' => \App\Models\InventoryItem::qty($percent)])">
        <x-slot:actions><a class="btn" href="{{ route('salary-advances.index') }}">{{ __('Back') }}</a></x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('salary-advances.mid-month.store') }}" class="card">
        @csrf
        <div class="toolbar">
            <div class="field" style="flex-direction:row;align-items:center;gap:10px"><label for="given_on">{{ __('Paid on') }}</label><input class="select" id="given_on" type="date" name="given_on" value="{{ $date->toDateString() }}" required></div>
            <div><span class="muted">{{ __('Total') }}:</span> <b class="num" id="midTotal">Rs 0</b></div>
        </div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Employee') }}</th><th class="r">{{ __('Salary') }}</th><th class="r">{{ __('Advance (Rs)') }}</th></tr></thead>
            <tbody>
            @forelse ($employees as $e)
                <tr>
                    <td><b>{{ $e->localName() }}</b><div class="meta">{{ $e->code }} · {{ $e->designation }}</div></td>
                    <td class="r num">Rs {{ number_format($e->monthly_salary) }}</td>
                    <td class="r">
                        @if ($already->has($e->id))
                            <x-badge tone="good">{{ __('Already given Rs :n', ['n' => number_format($already[$e->id])]) }}</x-badge>
                        @else
                            <input class="select mid-amount" type="number" min="0" step="10" name="amounts[{{ $e->id }}]" value="{{ old("amounts.{$e->id}", $e->suggested) }}" style="width:130px;text-align:end" aria-label="{{ __('Advance for :name', ['name' => $e->name]) }}">
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3"><div class="empty"><b>{{ __('No active staff') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="form-actions"><a class="btn" href="{{ route('salary-advances.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Record advances') }}</button></div>
    </form>
    @push('scripts')
        <script>
            (() => {
                const total = () => document.getElementById('midTotal').textContent = 'Rs ' + [...document.querySelectorAll('.mid-amount')].reduce((s, i) => s + (+i.value || 0), 0).toLocaleString('en-PK');
                document.querySelectorAll('.mid-amount').forEach(i => i.addEventListener('input', total));
                total();
            })();
        </script>
    @endpush
</x-layouts.app>
