<x-layouts.app :title="__('Books & formats')">
    <x-page-head :title="__('Books & formats')" :subtitle="__('Every book Etihad prints, with its formats, forms per copy and contractor rates.')">
        <x-slot:actions>@can('books.manage')<a class="btn primary" href="{{ route('books.create') }}"><x-icon name="plus" />{{ __('Add book') }}</a>@endcan</x-slot:actions>
    </x-page-head>

    <div class="card">
        <div class="toolbar">
            <h3 style="margin:0">{{ trans_choice(':count book|:count books', $books->total()) }}</h3>
            <form class="filters" method="GET">
                <input class="select" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search books') }}" aria-label="{{ __('Search books') }}" style="min-width:220px">
            </form>
        </div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Book') }}</th><th>{{ __('Publisher') }}</th><th>{{ __('Formats') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($books as $book)
                <tr>
                    <td>
                        <a href="{{ route('books.show', $book) }}" class="main" style="color:inherit;text-decoration:none">{{ $book->name }}</a>
                        <div class="meta">{{ collect([$book->name_ur, $book->class_subject])->filter()->join(' · ') }}</div>
                    </td>
                    <td>{{ $book->party?->name ?? '—' }}</td>
                    <td><div class="chips">
                        @foreach ($book->formats as $format)
                            <span class="tag">{{ $format->name }} · {{ trans_choice(':count form|:count forms', (int) ceil((float) $format->forms), ['count' => $format->formsLabel()]) }}</span>
                        @endforeach
                    </div></td>
                    <td><x-badge :tone="$book->is_active ? 'good' : 'mute'">{{ $book->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                    <td class="r" style="white-space:nowrap">
                        @can('books.manage')<a class="btn sm" href="{{ route('books.edit', $book) }}"><x-icon name="edit" />{{ __('Edit') }}</a>@endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><b>{{ $search ? __('No books match ":q"', ['q' => $search]) : __('No books yet') }}</b>{{ __('Add a book with its formats to use it in work orders.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $books->links() }}
    </div>
</x-layouts.app>
