<?php

namespace App\Http\Requests;

use App\Enums\PartyKind;
use App\Models\InventoryCategory;
use App\Models\Party;
use App\Support\OtherOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One purchase bill on a material tab: one supplier and date, several item lines (each with quantity and rate).
 * An item typed in under "Other…" joins that material (e.g. "Mitti ka tel" under Diesel); a supplier typed in
 * is added to Parties. Blank rows the form always shows are dropped.
 */
class StoreMaterialPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        OtherOption::resolve($this, 'party_id', Party::class, ['kind' => PartyKind::Supplier, 'is_active' => true]);

        /** @var InventoryCategory $category */
        $category = $this->route('inventory_category');

        $items = collect($this->input('items', []))
            ->filter(fn ($row) => is_array($row) && collect(['quantity', 'rate'])->contains(fn ($f) => trim((string) ($row[$f] ?? '')) !== ''))
            ->map(function (array $row) use ($category): array {
                if (($row['inventory_item_id'] ?? null) === OtherOption::Value) {
                    $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($row['item_other'] ?? '')));
                    $item = null;
                    if ($name !== '') {
                        $item = $category->items()->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->first()
                            ?? $category->items()->create(['name' => $name, 'unit' => $category->defaultUnit(), 'is_active' => true]);
                        if (! $item->is_active) {
                            $item->update(['is_active' => true]);
                        }
                    }
                    $row['inventory_item_id'] = $item?->getKey();
                }

                return ['inventory_item_id' => $row['inventory_item_id'] ?? null, 'quantity' => $row['quantity'] ?? null, 'rate' => $row['rate'] ?? null];
            })
            ->values();

        $this->merge(['items' => $items->all()]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['items.*.inventory_item_id' => __('item'), 'items.*.quantity' => __('quantity'), 'items.*.rate' => __('rate')];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var InventoryCategory $category */
        $category = $this->route('inventory_category');

        return [
            'purchased_on' => ['required', 'date', 'before_or_equal:today'],
            'party_id' => ['required', 'exists:parties,id'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('inventory_category_id', $category->id)->where('is_active', true)],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'bill_no' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ];
    }
}
