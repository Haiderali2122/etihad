<x-layouts.app :title="__('Designations')">
    <x-page-head :title="__('Designations')" :subtitle="__('Job titles offered when adding or editing an employee.')">
        <x-slot:actions>
            <a class="btn" href="{{ route('employees.index') }}">{{ __('Back to staff') }}</a>
            <a class="btn primary" href="{{ route('designations.create') }}"><x-icon name="plus" />{{ __('Add designation') }}</a>
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="card"><div class="tbl-wrap"><table>
        <thead><tr><th>{{ __('Designation') }}</th><th class="r">{{ __('Active staff') }}</th><th class="r">{{ __('All staff') }}</th><th>{{ __('Notes') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($designations as $designation)
            <tr>
                <td><div class="main">{{ $designation->name }}</div>@if ($designation->name_ur)<div class="meta" style="font-family:var(--urdu)" dir="rtl">{{ $designation->name_ur }}</div>@endif</td>
                <td class="r num"><b>{{ number_format($designation->active_employees_count) }}</b></td>
                <td class="r num">@if ($designation->employees_count)<a href="{{ route('employees.index', ['status' => 'all', 'q' => $designation->name]) }}">{{ number_format($designation->employees_count) }}</a>@else 0 @endif</td>
                <td>{{ $designation->notes ?: '—' }}</td>
                <td><x-badge :tone="$designation->is_active ? 'good' : 'mute'">{{ $designation->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                <td class="r" style="white-space:nowrap">
                    <a class="btn sm" href="{{ route('designations.edit', $designation) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                    @if ($designation->employees_count === 0)
                        <form method="POST" action="{{ route('designations.destroy', $designation) }}" style="display:inline" onsubmit="return confirm(@js(__('Delete :name?', ['name' => $designation->name])))">
                            @csrf @method('DELETE')
                            <button class="btn sm" type="submit" style="color:var(--bad)" aria-label="{{ __('Delete :name', ['name' => $designation->name]) }}"><x-icon name="x" /></button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty"><b>{{ __('No designations yet') }}</b>{{ __('Add job titles such as Printer, Binder or Helper.') }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.app>
