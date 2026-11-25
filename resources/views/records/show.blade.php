{{-- A record's details, read only: every field, its file, its lines and payments. Nothing here can be changed. --}}
<x-layouts.app :title="$title">
    <x-page-head :back="$back" :title="$title" />

    <div class="grid g-main">
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Details') }}</h3></div></div>
            <div class="card-b">
                <dl class="detail-list">
                    @foreach ($fields as [$label, $value])
                        <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
                    @endforeach
                </dl>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:18px">
            @if ($attachment)
                <div class="card">
                    <div class="card-h"><div><h3>{{ __('Attached file') }}</h3></div></div>
                    <div class="card-b">
                        @if ($attachment['image'])
                            <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener"><img src="{{ $attachment['url'] }}" alt="{{ __('Attached file') }}" style="max-width:100%;border-radius:10px;border:1px solid var(--line)"></a>
                        @else
                            <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener">{{ __('Open the file') }}</a>
                        @endif
                    </div>
                </div>
            @endif
            @if ($cashOut || $source)
                <div class="card">
                    <div class="card-h"><div><h3>{{ __('Linked') }}</h3></div></div>
                    <div class="card-b">
                        @if ($cashOut)<div class="stat-line"><span>{{ __('Cash-out') }}</span><b>{{ $cashOut->receiptNumber() }} · Rs {{ number_format($cashOut->amount) }}</b></div>@endif
                        @if ($source)<div class="stat-line"><span>{{ __('Made on') }}</span><b><a href="{{ $source->cashOutUrl() }}">{{ $source->cashOutSource() }}</a></b></div>@endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @foreach ($lists as $name => $rows)
        <div class="card">
            <div class="card-h"><div><h3>{{ $name }}</h3><div class="sub">{{ trans_choice(':count line|:count lines', count($rows)) }}</div></div></div>
            <div class="tbl-wrap"><table>
                <thead><tr>@foreach ($rows[0] as [$label]) <th>{{ $label }}</th> @endforeach</tr></thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>@foreach ($row as [, $value]) <td>{{ $value }}</td> @endforeach</tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @endforeach
</x-layouts.app>
