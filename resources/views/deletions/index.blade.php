<x-layouts.app :title="__('Deletion log')">
    <x-page-head :title="__('Deletion log')" :subtitle="__('Every record that has been deleted, the reason given, and who deleted it.')" />

    <div class="card"><div class="tbl-wrap"><table>
        <thead><tr>
            <th>{{ __('When') }}</th><th>{{ __('Type') }}</th><th>{{ __('Record') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Deleted by') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($deletions as $d)
            <tr>
                <td>{{ $d->created_at?->format('j M Y, g:i a') ?? '—' }}</td>
                <td><x-badge tone="mute">{{ $d->kind }}</x-badge></td>
                <td><b>{{ $d->label }}</b></td>
                <td>{{ $d->note ?: '—' }}</td>
                <td>{{ $d->deleter?->name ?? $d->deleted_by_name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty"><b>{{ __('Nothing deleted yet') }}</b>{{ __('Deleted records will be listed here with their reason.') }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($deletions->hasPages())<div class="card-b">{{ $deletions->links() }}</div>@endif
    </div>
</x-layouts.app>
