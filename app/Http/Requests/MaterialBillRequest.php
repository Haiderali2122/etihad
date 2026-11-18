<?php

namespace App\Http\Requests;

use App\Models\InventoryCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * A Diesel, Glue or Ink bill: the same rows as the tab's expense form (item, quantity, price per quantity), the
 * supplier, and optionally what was paid there and then. The rest stays unpaid on the bill.
 */
class MaterialBillRequest extends StoreExpenseRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = InventoryCategory::query()->where('show_as_tab', true)->find($this->integer('inventory_category_id'));

        return [
            ...parent::rules(),
            'inventory_category_id' => ['required', Rule::exists('inventory_categories', 'id')->where('show_as_tab', true),
                fn (string $attribute, mixed $value, \Closure $fail) => $category?->usesQuantity() ? null : $fail(__('Bills are only for Diesel, Glue and Ink.'))],
            'paid_now' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Lines with their totals; the bill total is their sum.
     *
     * @return list<array{item: ?string, quantity: string|float, rate: string|float, amount: int}>
     */
    public function lines(): array
    {
        return collect($this->validated('items'))->map(fn (array $i) => [
            'item' => $i['item'] ?? $i['description'] ?? null, 'quantity' => $i['quantity'], 'rate' => $i['rate'], 'amount' => (int) $i['amount'],
        ])->all();
    }
}
