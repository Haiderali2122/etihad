<x-layouts.app :title="__('Inventory categories')">
    <x-page-head :title="__('Inventory categories')" :subtitle="__('Groups for inventory items, e.g. Ink, Plates, Glue, Machine parts.')">
        <x-slot:actions>
            <a class="btn" href="{{ route('inventory.index') }}">{{ __('Back to inventory') }}</a>
            <a class="btn primary" href="{{ route('inventory-categories.create') }}"><x-icon name="plus" />{{ __('Add category') }}</a>
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="card"><div class="tbl-wrap"><table>
        <thead><tr><th>{{ __('Category') }}</th><th>{{ __('Name in Urdu') }}</th><th>{{ __('Stock') }}</th><th class="r">{{ __('Items') }}</th><th class="r">{{ __('Purchased') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($categories as $category)
            <tr>
                <td><a class="main" href="{{ route('inventory.index', ['category' => $category->id]) }}" style="color:inherit;text-decoration:none">{{ $category->name }}</a></td>
                <td><span style="font-family:var(--urdu)" dir="rtl">{{ $category->name_ur ?: '—' }}</span></td>
                <td>{{ $category->tracks_stock ? __('Tracked here') : __('Bills only') }}</td>
                <td class="r num">{{ $category->items_count }}</td>
                <td class="r num">Rs {{ number_format($purchased[$category->id] ?? 0) }}</td>
                <td><x-badge :tone="$category->is_active ? 'good' : 'mute'">{{ $category->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                <td class="r" style="white-space:nowrap">
                    <a class="btn sm" href="{{ route('inventory-categories.edit', $category) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                    @if ($category->items_count === 0)
                        <form method="POST" action="{{ route('inventory-categories.destroy', $category) }}" style="display:inline" onsubmit="return confirm(@js(__('Delete :name?', ['name' => $category->name])))">
                            @csrf @method('DELETE')
                            <button class="btn sm" type="submit" style="color:var(--bad)" aria-label="{{ __('Delete :name', ['name' => $category->name]) }}"><x-icon name="x" /></button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty"><b>{{ __('No categories yet') }}</b></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.app>
