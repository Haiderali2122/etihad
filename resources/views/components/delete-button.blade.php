@props([
    'action',
    'confirm' => null,
    'label' => null,
    'icon' => 'x',
    'small' => false,
    'danger' => true,
])
{{--
    A delete button that asks for a reason before submitting. Shows only for roles with the "Delete records"
    permission; the GuardDeletions middleware enforces the same, so it cannot be skipped.
--}}
@can('records.delete')
    <form method="POST" action="{{ $action }}" class="delete-form" style="display:inline" data-confirm="{{ $confirm ?? __('Delete this record? This cannot be undone.') }}">
        @csrf
        @method('DELETE')
        <input type="hidden" name="delete_note" value="">
        <button type="submit" {{ $attributes->class(['btn', 'sm' => $small])->merge($danger ? ['style' => 'color:var(--bad)'] : []) }}>
            @if ($icon)<x-icon :name="$icon" />@endif{{ $label ?? $slot }}
        </button>
    </form>
@endcan
