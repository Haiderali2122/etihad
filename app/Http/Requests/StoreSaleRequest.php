<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A raddi sale slip: the date, one row per item with quantity and price per quantity, and an optional photo.
 * Each row's total is quantity × price; empty rows the form always shows at the end are dropped.
 */
class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($row) => is_array($row) && collect(['item', 'quantity', 'rate'])->contains(fn ($f) => trim((string) ($row[$f] ?? '')) !== ''))
            ->map(fn (array $row) => [
                'item' => trim((string) preg_replace('/\s+/u', ' ', (string) ($row['item'] ?? ''))),
                'quantity' => $row['quantity'] ?? null,
                'rate' => $row['rate'] ?? null,
            ])
            ->values();

        $this->merge(['items' => $items->all()]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['items.*.item' => __('item'), 'items.*.quantity' => __('quantity'), 'items.*.rate' => __('price per quantity')];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sold_on' => ['required', 'date', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item' => ['required', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ];
    }
}
