<x-layouts.app :title="__('Attendance machine')">
    <x-page-head :back="route('attendance.index')" :title="__('Attendance machine')" :subtitle="__('Punches come straight from the machine. Link each person on the machine to an employee once; after that their days fill in by themselves.')" />

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Machines') }}</h3><div class="sub">{{ __('A machine shows here the first time it calls in. Approve it to start receiving its punches.') }}</div></div></div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Serial number') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last seen') }}</th><th class="r">{{ __('People') }}</th><th class="r">{{ __('Punches') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($devices as $d)
                <tr>
                    <td><b>{{ $d->serial_number }}</b>@if ($d->name)<div class="meta">{{ $d->name }}</div>@endif
                        @if ($d->pending_commands)<div class="meta">{{ trans_choice(':count request waiting to be sent|:count requests waiting to be sent', count($d->pending_commands)) }}</div>@endif</td>
                    <td>
                        @if (! $d->is_approved)<x-badge tone="warn">{{ __('Waiting for approval') }}</x-badge>
                        @elseif ($d->isOnline())<x-badge tone="good">{{ __('Connected') }}</x-badge>
                        @else<x-badge tone="bad">{{ __('Not connected') }}</x-badge>@endif
                    </td>
                    @php $onMachine = (int) (explode(',', (string) $d->info)[3] ?? 0); @endphp
                    <td>{{ $d->last_seen_at ? $d->last_seen_at->diffForHumans() : '—' }}
                        @if ($onMachine > 0)<div class="meta">{{ __(':n punches on the machine, :m received', ['n' => number_format($onMachine), 'm' => number_format($d->punches()->count())]) }}</div>@endif
                        @if ($d->last_seen_at)<div class="meta">{{ $d->last_seen_at->format('j M, g:i A') }}</div>@endif</td>
                    <td class="r num">{{ $users->where('attendance_device_id', $d->id)->count() }}</td>
                    <td class="r num">{{ number_format($d->punches()->count()) }}</td>
                    <td class="r" style="white-space:nowrap">
                        <form method="POST" action="{{ route('attendance.machine.approve', $d) }}" class="inline-form">@csrf @method('PATCH')
                            <input type="hidden" name="approved" value="{{ $d->is_approved ? 0 : 1 }}">
                            <button class="btn sm {{ $d->is_approved ? '' : 'good' }}" type="submit">@if ($d->is_approved){{ __('Switch off') }}@else<x-icon name="check" />{{ __('Approve') }}@endif</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><b>{{ __('No machine has called in yet') }}</b>{{ __('On the machine: Menu → Comm. → Cloud Server Setting. Server mode ADMS, Enable Domain Name on, Server Address :host, Server port 80, Proxy off. Then restart the machine.', ['host' => $serverHost]) }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    @foreach ($devices->where('is_approved', true) as $d)
        {{-- This machine does not send punches by itself; a small program on an office computer reads it and sends them. --}}
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Reader program (office computer)') }}</h3><div class="sub">{{ __('Runs on a computer on the same Wi-Fi as the machine. It reads the machine every few minutes and sends new punches here.') }}</div></div></div>
            <div class="card-b">
                <div class="stat-line"><span>{{ __('Last sent') }}</span><b>@if ($d->last_synced_at){{ $d->last_synced_at->diffForHumans() }} · {{ $d->last_synced_at->format('j M, g:i A') }} · {{ $d->last_sync_result }}@else<span class="muted">{{ __('Never') }}</span>@endif</b></div>
                <ol style="margin:12px 0 0;padding-inline-start:20px;line-height:1.9">
                    <li>{{ __('On the office computer (on the same Wi-Fi as the machine), open this page and download the program:') }}
                        <a class="btn sm primary" href="{{ route('attendance.machine.reader-program', $d) }}"><x-icon name="download" />{{ __('Download reader program') }}</a></li>
                    <li>{{ __('Double-click the downloaded file once, without renaming it. If Windows says "Windows protected your PC", click More info, then Run anyway.') }}</li>
                    <li>{{ __('It installs itself, starts with Windows and reads the machine every 5 minutes in the background. "Last sent" above shows it working.') }}</li>
                </ol>
                <p class="muted" style="margin:10px 0 0">{{ __('The file name holds the secret key for this machine: keep it on that computer only.') }}</p>
            </div>
        </div>
    @endforeach

    <div class="card">
        <div class="card-h"><div><h3>{{ __('People on the machine') }}</h3><div class="sub">{{ __('Everyone on the machine is added to Staff (salary 0 until you fill it in). Change the employee here if someone was matched wrongly.') }}</div></div>
            @can('staff.manage')@if ($users->whereNull('employee_id')->isNotEmpty())
                <form method="POST" action="{{ route('attendance.machine.add-all') }}" class="inline-form">@csrf
                    <button class="btn sm primary" type="submit"><x-icon name="plus" />{{ __('Add everyone to Staff') }}</button>
                </form>
            @endif @endcan
        </div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Machine no.') }}</th><th>{{ __('Name on machine') }}</th><th>{{ __('Employee') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($users as $u)
                <tr>
                    <td class="num"><b>{{ $u->pin }}</b></td>
                    <td>{{ $u->name ?: '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('attendance.machine.link', $u) }}" class="filters" style="flex-wrap:nowrap">@csrf @method('PATCH')
                            <select class="select" name="employee_id" aria-label="{{ __('Employee') }}" onchange="this.form.submit()">
                                <option value="">{{ __('— Not linked —') }}</option>
                                @foreach ($employees as $e)<option value="{{ $e->id }}" @selected($u->employee_id === $e->id)>{{ $e->name }}@unless ($e->is_active) ({{ __('inactive') }})@endunless</option>@endforeach
                            </select>
                        </form>
                    </td>
                    <td class="r">@unless ($u->employee_id)@can('staff.manage')<a class="btn sm" href="{{ route('employees.create', ['name' => $u->name, 'device_code' => $u->pin]) }}"><x-icon name="plus" />{{ __('Create employee') }}</a>@endcan @endunless</td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty"><b>{{ __('No people yet') }}</b>{{ __('They arrive from the machine once it is approved.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Latest punches') }}</h3></div></div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Machine no.') }}</th><th>{{ __('Person') }}</th></tr></thead>
            <tbody>
            @forelse ($punches as $p)
                <tr><td>{{ $p->punched_at->format('j M Y, g:i A') }}</td><td class="num">{{ $p->pin }}</td><td>{{ $names[$p->pin] ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="3"><div class="empty"><b>{{ __('No punches yet') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</x-layouts.app>
