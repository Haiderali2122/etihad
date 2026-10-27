<x-layouts.app :title="$book->name">
    <x-page-head :title="$book->name" :subtitle="collect([$book->name_ur, $book->class_subject, $book->party?->name])->filter()->join(' · ')">
        <x-slot:actions>
            <x-badge :tone="$book->is_active ? 'good' : 'mute'">{{ $book->is_active ? __('Active') : __('Inactive') }}</x-badge>
            <a class="btn" href="{{ route('books.index') }}">{{ __('Back') }}</a>
            @can('books.manage')
            <form method="POST" action="{{ route('books.destroy', $book) }}" onsubmit="return confirm(@js(__('Delete :name and all its formats?', ['name' => $book->name])))">
                @csrf @method('DELETE')
                <button class="btn" type="submit" style="color:var(--bad)"><x-icon name="x" />{{ __('Delete') }}</button>
            </form>
            @endcan
            @can('books.manage')<a class="btn primary" href="{{ route('books.edit', $book) }}"><x-icon name="edit" />{{ __('Edit') }}</a>@endcan
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Formats') }}</h3><div class="sub">{{ __('Example: rims and contractor amounts for 10,000 copies at 3% wastage') }}</div></div></div>
        <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
            <thead><tr>
                <th>{{ __('Format') }}</th><th class="r">{{ __('Forms') }}</th><th class="r">{{ __('Pages') }}</th><th>{{ __('Default paper') }}</th>
                <th class="r">{{ __('Folding rate') }}</th><th class="r">{{ __('Binding rate') }}</th>
                <th class="r">{{ __('Rims / 10,000') }}</th><th class="r">{{ __('Folding / 10,000') }}</th>
            </tr></thead>
            <tbody>
            @foreach ($book->formats as $format)
                <tr>
                    <td><div class="main">{{ $format->name }}</div><div class="meta">{{ $format->size }}</div></td>
                    <td class="r num"><b>{{ $format->formsLabel() }}</b></td>
                    <td class="r num">{{ $format->pages ?: '—' }}</td>
                    <td>{{ $format->paperType?->label() ?? '—' }}</td>
                    <td class="r num">{{ $format->folding_rate !== null ? 'Rs '.number_format((float) $format->folding_rate, 2) : '—' }}<div class="meta">{{ __('per 1,000 forms') }}</div></td>
                    <td class="r num">{{ $format->binding_rate !== null ? 'Rs '.number_format((float) $format->binding_rate, 2) : '—' }}<div class="meta">{{ __('per copy') }}</div></td>
                    <td class="r num">{{ number_format(\App\Support\PressMath::rimsFor((float) $format->forms, 10000, 3, $format->paperType?->sheets_per_unit ?? 500)) }}</td>
                    <td class="r num">{{ $format->foldingAmount(10000) !== null ? 'Rs '.number_format($format->foldingAmount(10000)) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div></div>
    </div>

    @if ($book->notes)
        <div class="card"><div class="card-b">{{ $book->notes }}</div></div>
    @endif
</x-layouts.app>
