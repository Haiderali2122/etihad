@php $result = session('import'); @endphp
<x-layouts.app :title="__('Import attendance')">
    <x-page-head :title="__('Import attendance from the device')" :subtitle="__('Export the attendance log from the biometric device as a CSV file and upload it here.')">
        <x-slot:actions><a class="btn" href="{{ route('attendance.index') }}">{{ __('Month view') }}</a></x-slot:actions>
    </x-page-head>

    @if ($result)
        <div class="card"><div class="card-b">
            <div class="stat-line"><span>{{ __('Days saved') }}</span><b class="num">{{ $result['saved'] }}</b></div>
            <div class="stat-line"><span>{{ __('Skipped (already marked by hand)') }}</span><b class="num">{{ $result['skipped'] }}</b></div>
            @if ($result['unknown'])
                <div class="stat-line"><span>{{ __('Employee numbers not found') }}</span><b>{{ implode(', ', array_slice($result['unknown'], 0, 20)) }}</b></div>
            @endif
            @foreach (array_slice($result['errors'], 0, 10) as $error)<div class="stat-line" style="color:var(--bad)">{{ $error }}</div>@endforeach
        </div></div>
    @endif

    <form method="POST" action="{{ route('attendance.import.store') }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        <div class="card-b"><div class="form">
            <x-field name="file" type="file" :label="__('Attendance file (CSV)')" full required accept=".csv,text/csv" />
            <div class="field full"><input type="hidden" name="overwrite" value="0"><label class="check-row"><input type="checkbox" name="overwrite" value="1"> {{ __('Replace days already marked by hand') }}</label></div>
        </div></div>
        <div class="card-b" style="padding-top:0">
            <div class="calc">
                <div class="lbl">{{ __('Accepted layouts (first row is the header)') }}</div>
                <div class="eq" dir="ltr"><b>{{ __('One row per punch') }}:</b> code, datetime<br>E-001, 2026-10-12 08:58<br>E-001, 2026-10-12 21:04</div>
                <div class="eq" dir="ltr"><b>{{ __('One row per day') }}:</b> code, date, in, out<br>E-001, 2026-10-12, 08:58, 21:04</div>
                <div class="lbl">{{ __('The first and last punch of a day become in and out. Time beyond :h hours counts as overtime. Device column names such as AC-No. and Time are recognised.', ['h' => \App\Models\InventoryItem::qty($standardHours)]) }}</div>
            </div>
        </div>
        <div class="form-actions"><button class="btn primary" type="submit"><x-icon name="download" />{{ __('Import') }}</button></div>
    </form>
</x-layouts.app>
