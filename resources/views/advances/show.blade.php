<x-layouts.app :title="__('Advance receipt')">
    <x-page-head :title="__('Advance receipt')" :subtitle="$advance->employee->name.', '.$advance->given_on->format('j M Y')">
        <x-slot:actions>
            @if ($advance->isDeducted())<x-badge tone="mute">{{ __('Deducted in :month', ['month' => $advance->payrollRun->label()]) }}</x-badge>@else<x-badge :tone="$advance->status->tone()">{{ $advance->status->label() }}</x-badge>@endif
            <a class="btn" href="{{ route('salary-advances.index') }}">{{ __('Back') }}</a>
            @can('advances.create')<a class="btn" href="{{ $advance->foreman_sheet_id ? route('foreman-sheets.edit', $advance->foreman_sheet_id) : route('salary-advances.edit', $advance) }}"><x-icon name="edit" />{{ __('Edit') }}</a>@endcan
            @unless ($advance->foreman_sheet_id)@can('advances.create')<x-delete-button :action="route('records.destroy', ['salary-advance', $advance->id])" :confirm="__('Delete this advance?')" :label="__('Delete')" :redirect="route('salary-advances.index')" />@endcan @endunless
            <button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-head>
    @if ($errors->any())<div class="flash err no-print" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif
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
