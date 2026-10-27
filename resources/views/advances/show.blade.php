<x-layouts.app :title="__('Advance receipt')">
    <x-page-head :title="__('Advance receipt')" :subtitle="$advance->employee->name.', '.$advance->given_on->format('j M Y')">
        <x-slot:actions>
            @if ($advance->isDeducted())<x-badge tone="mute">{{ __('Deducted in :month', ['month' => $advance->payrollRun->label()]) }}</x-badge>@else<x-badge :tone="$advance->status->tone()">{{ $advance->status->label() }}</x-badge>@endif
            <a class="btn" href="{{ route('salary-advances.index') }}">{{ __('Back') }}</a>
            <button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-head>
    @include('advances.receipt', [
        'title' => 'ADVANCE RECEIPT', 'titleUr' => 'رسید ایڈوانس', 'no' => 'ADV-'.str_pad((string) $advance->id, 5, '0', STR_PAD_LEFT),
        'lines' => [
            ['تاریخ', 'Date', $advance->given_on->format('j M Y')],
            ['نام', 'Name', $advance->employee->name.' ('.$advance->employee->code.')'],
            ['عہدہ', 'Designation', $advance->employee->designation],
            ['رقم', 'Amount', 'Rs '.number_format($advance->amount)],
            ['قسم', 'Type', $advance->kind->label().($advance->foremanSheet ? ' — '.$advance->foremanSheet->foreman->name : '')],
            ['نوٹ', 'Note', $advance->note ?: 'To be deducted from salary'],
        ],
    ])
</x-layouts.app>
