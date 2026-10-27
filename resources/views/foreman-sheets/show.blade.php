<x-layouts.app :title="__('Foreman advance sheet')">
    <x-page-head :title="__('Foreman sheet: :name', ['name' => $sheet->foreman->name])" :subtitle="__('Rs :amount given on :date', ['amount' => number_format($sheet->amount), 'date' => $sheet->given_on->format('j M Y')])">
        <x-slot:actions>
            <x-badge :tone="$sheet->status->tone()">{{ $sheet->status->label() }}</x-badge>
            <a class="btn" href="{{ route('salary-advances.index') }}">{{ __('Back') }}</a>
            <button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-head>
    @if ($sheet->review_note)<div class="alert"><x-icon name="alert" /><div><b>{{ __('Owner note') }}:</b> {{ $sheet->review_note }}</div></div>@endif
    <div class="card">
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Note') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Signature') }}</th></tr></thead>
            <tbody>
            @foreach ($sheet->advances as $a)
                <tr>
                    <td><b>{{ $a->employee->name }}</b><div class="meta">{{ $a->employee->code }}</div></td>
                    <td>{{ $a->note ?: '—' }}</td>
                    <td class="r num"><b>Rs {{ number_format($a->amount) }}</b></td>
                    <td style="min-width:140px;border-bottom:1px dotted var(--line)">&nbsp;</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td>{{ __('Total') }}</td><td></td><td class="r num">Rs {{ number_format($sheet->advances->sum('amount')) }}</td><td></td></tr></tfoot>
        </table></div>
    </div>
</x-layouts.app>
