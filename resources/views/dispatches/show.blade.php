<x-layouts.app :title="__('Loading slip :n', ['n' => $dispatch->number])">
    <x-page-head :title="__('Loading slip :n', ['n' => $dispatch->number])" :subtitle="$dispatch->buyer->name.', '.$dispatch->dispatched_on->format('j M Y')">
        <x-slot:actions>
            <a class="btn" href="{{ route('dispatches.index') }}">{{ __('Back') }}</a>
            <button class="btn primary" type="button" onclick="window.print()"><x-icon name="printer" />{{ __('Print') }}</button>
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
            $dispatch->rims !== null ? ['رم', 'Rims', \App\Models\InventoryItem::qty($dispatch->rims)] : null,
            ['گاڑی نمبر', 'Truck no.', $dispatch->truck_no ?: '—'],
            ['مزدوری', 'Loading labour', 'Rs '.number_format($dispatch->labour_amount).($dispatch->labour_paid_to ? ' — '.$dispatch->labour_paid_to : '')],
        ])),
    ])
</x-layouts.app>
