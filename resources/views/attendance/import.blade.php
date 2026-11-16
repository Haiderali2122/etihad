@php $result = session('import'); @endphp
<x-layouts.app :title="__('Import attendance')">
    <x-page-head :title="__('Import attendance from the machine')" :subtitle="__('Upload the file exported from the attendance machine software (the .xls from its Export button). Each person\'s present or absent days and overtime are saved; a file with one day imports just that day.')">
        <x-slot:actions><a class="btn" href="{{ route('attendance.index') }}">{{ __('Month view') }}</a></x-slot:actions>
    </x-page-head>

    @if ($result)
        <div class="card"><div class="card-b">
            <div class="stat-line"><span>{{ __('Days saved') }}</span><b class="num">{{ $result['saved'] }}</b></div>
            <div class="stat-line"><span>{{ __('Present') }}</span><b class="num">{{ $result['present'] ?? 0 }}</b></div>
            <div class="stat-line"><span>{{ __('Absent') }}</span><b class="num">{{ $result['absent'] ?? 0 }}</b></div>
            <div class="stat-line"><span>{{ __('Skipped (already marked by hand)') }}</span><b class="num">{{ $result['skipped'] }}</b></div>
            @if ($result['unknown'])
                <div class="stat-line" style="color:var(--warn)"><span>{{ __('Not matched to any employee (add them under Staff with this machine no., then import again)') }}</span><b>{{ implode(', ', array_slice($result['unknown'], 0, 30)) }}</b></div>
            @endif
            @foreach (array_slice($result['errors'], 0, 10) as $error)<div class="stat-line" style="color:var(--bad)">{{ $error }}</div>@endforeach
            @if (! empty($result['from']))
                <div style="margin-top:12px"><a class="btn primary" href="{{ route('attendance.index', ['month' => \Illuminate\Support\Carbon::parse($result['from'])->format('Y-m')]) }}"><x-icon name="cal" />{{ __('See :month attendance', ['month' => \Illuminate\Support\Carbon::parse($result['from'])->format('F Y')]) }}</a></div>
            @endif
        </div></div>
    @endif

    <form method="POST" action="{{ route('attendance.import.store') }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        <div class="card-b"><div class="form">
            <x-field name="file" type="file" :label="__('Attendance file (.xls or .csv)')" full required accept=".xls,.csv,text/csv,application/vnd.ms-excel" />
            <div class="field full"><input type="hidden" name="overwrite" value="0"><label class="check-row"><input type="checkbox" name="overwrite" value="1"> {{ __('Replace days already changed by hand') }}</label></div>
        </div></div>
        <div class="card-b" style="padding-top:0">
            <div class="calc">
                <div class="lbl">{{ __('How the file is read') }}</div>
                <div class="eq">{{ __('People are matched by their attendance machine no. (AC-No.), then by employee no., then by name.') }}</div>
                <div class="eq">{{ __('A day with Clock In or Clock Out is present, with the file\'s OT Time as overtime; a day marked Absent is absent.') }}</div>
                <div class="eq">{{ __('Days you changed by hand on the attendance sheet are kept unless you tick the box above.') }}</div>
            </div>
        </div>
        <div class="form-actions"><button class="btn primary" type="submit"><x-icon name="download" />{{ __('Import') }}</button></div>
    </form>
</x-layouts.app>
