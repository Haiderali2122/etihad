@php $employees ??= collect(); @endphp
<tr>
    <td>
        <select name="lines[{{ $i }}][employee_id]" aria-label="{{ __('Employee') }}" style="min-width:200px">
            <option value="">{{ __('Choose employee') }}</option>
            @foreach ($employees as $e)<option value="{{ $e->id }}" @selected((string) ($line['employee_id'] ?? '') === (string) $e->id)>{{ $e->name }} ({{ $e->code }})</option>@endforeach
        </select>
    </td>
    <td><input type="number" min="1" name="lines[{{ $i }}][amount]" value="{{ $line['amount'] ?? '' }}" aria-label="{{ __('Amount (Rs)') }}"></td>
    <td><button type="button" class="icon-btn danger" data-remove aria-label="{{ __('Remove') }}"><x-icon name="x" /></button></td>
</tr>
