@php
    $err = fn (string $field) => $errors->first("formats.{$i}.{$field}");
    $paperTypes ??= collect();
    $trim = fn ($v) => is_numeric($v) && str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : $v;
@endphp
<tr>
    <td>
        @if (! empty($row['id']))<input type="hidden" name="formats[{{ $i }}][id]" value="{{ $row['id'] }}">@endif
        <input name="formats[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="{{ __('Standard') }}" aria-label="{{ __('Format name') }}" required style="min-width:130px">
        @if ($err('name'))<span class="err">{{ $err('name') }}</span>@endif
    </td>
    <td><input name="formats[{{ $i }}][size]" value="{{ $row['size'] ?? '' }}" placeholder="20 × 30" aria-label="{{ __('Size') }}"></td>
    <td>
        <input type="number" step="0.5" min="0.5" name="formats[{{ $i }}][forms]" value="{{ $trim($row['forms'] ?? '') }}" placeholder="10" aria-label="{{ __('Forms') }}" required>
        @if ($err('forms'))<span class="err">{{ $err('forms') }}</span>@endif
    </td>
    <td><input type="number" min="1" name="formats[{{ $i }}][pages]" value="{{ $row['pages'] ?? '' }}" aria-label="{{ __('Pages') }}"></td>
    <td>
        <select name="formats[{{ $i }}][paper_type_id]" aria-label="{{ __('Default paper') }}" style="min-width:150px">
            <option value="">—</option>
            @foreach ($paperTypes as $type)
                <option value="{{ $type->id }}" @selected((string) ($row['paper_type_id'] ?? '') === (string) $type->id)>{{ $type->code }} {{ $type->name }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="number" step="0.01" min="0" name="formats[{{ $i }}][folding_rate]" value="{{ $row['folding_rate'] ?? '' }}" placeholder="35" aria-label="{{ __('Folding rate') }}">
        @if ($err('folding_rate'))<span class="err">{{ $err('folding_rate') }}</span>@endif</td>
    <td><input type="number" step="0.01" min="0" name="formats[{{ $i }}][binding_rate]" value="{{ $row['binding_rate'] ?? '' }}" placeholder="2.5" aria-label="{{ __('Binding rate') }}">
        @if ($err('binding_rate'))<span class="err">{{ $err('binding_rate') }}</span>@endif</td>
    <td><button type="button" class="icon-btn danger" data-remove aria-label="{{ __('Remove format') }}"><x-icon name="x" /></button></td>
</tr>
