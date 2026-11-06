<x-layouts.app :title="__('Loading slip :n', ['n' => $dispatch->number])">
    <x-page-head :title="__('Loading slip :n', ['n' => $dispatch->number])" :subtitle="$dispatch->buyer->name.', '.$dispatch->dispatched_on->format('j M Y')">
        <x-slot:actions>
            <a class="btn" href="{{ route('dispatches.index') }}">{{ __('Back') }}</a>
            @can('dispatch.manage')<a class="btn" href="{{ route('dispatches.edit', $dispatch) }}"><x-icon name="edit" />{{ __('Edit') }}</a>@endcan
            @can('dispatch.manage')<x-delete-button :action="route('records.destroy', ['dispatch', $dispatch->id])" :confirm="__('Delete loading :no?', ['no' => $dispatch->number])" :label="__('Delete')" :redirect="route('dispatches.index')" />@endcan
            <button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print / save as PDF') }}</button>
        </x-slot:actions>
    </x-page-head>
    @include('advances.receipt', [
        'title' => 'LOADING SLIP', 'titleUr' => 'لوڈنگ سلپ', 'no' => $dispatch->number,
        'lines' => array_values(array_filter([
            ['تاریخ', 'Date', $dispatch->dispatched_on->format('j M Y')],
            ['خریدار', 'Buyer', $dispatch->buyer->name.($dispatch->destination ? ' — '.$dispatch->destination : '')],
            ['کتاب', 'Book', $dispatch->bookName().($dispatch->workOrder ? ' ('.$dispatch->workOrder->number.')' : '')],
            ['بنڈل', 'Bundles', number_format($dispatch->bundles).' × '.$dispatch->copies_per_bundle.($dispatch->loose_copies ? ' + '.$dispatch->loose_copies.' loose' : '')],
            ['کل کاپیاں', 'Total copies', number_format($dispatch->copies)],
            ['گاڑی نمبر', 'Truck no.', $dispatch->truck_no ?: '—'],
            ['مزدوری', 'Loading labour', 'Rs '.number_format($dispatch->labour_amount).($dispatch->labour_paid_to ? ' — '.$dispatch->labour_paid_to : '')],
            $dispatch->note ? ['نوٹ', 'Note', $dispatch->note] : null,
        ])),
    ])

    @if ($dispatch->truck_front || $dispatch->truck_back)
        <div style="max-width:720px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            @foreach (['truck_front' => __('Truck, front'), 'truck_back' => __('Truck, back')] as $photo => $label)
                @if ($dispatch->{$photo})
                    <figure class="card" style="margin:0;padding:10px">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($dispatch->{$photo}) }}" alt="{{ $label }}" style="width:100%;border-radius:8px;display:block">
                        <figcaption class="meta" style="margin-top:6px">{{ $label }}</figcaption>
                    </figure>
                @endif
            @endforeach
        </div>
    @endif

    @if ($dispatch->files)
        <div class="card no-print" style="max-width:720px">
            <div class="card-h"><div><h3>{{ __('Other files') }}</h3></div></div>
            <div class="card-b" style="display:flex;flex-wrap:wrap;gap:8px">
                @foreach ($dispatch->files as $i => $file)
                    <a class="btn sm" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($file) }}" target="_blank" rel="noopener"><x-icon name="file" />{{ __('File :n', ['n' => $i + 1]) }}</a>
                @endforeach
            </div>
        </div>
    @endif
</x-layouts.app>
