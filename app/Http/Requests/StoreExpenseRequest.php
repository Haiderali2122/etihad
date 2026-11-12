<?php

namespace App\Http\Requests;

use App\Enums\PartyKind;
use App\Models\ExpenseHead;
use App\Models\InventoryCategory;
use App\Models\Party;
use App\Support\OtherOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Each row is an item and its price (and, on a material tab, its quantity). The item is typed or picked from
     * the suggestions: in Cash-out it is the expense head (a new name is added to the heads); on the Diesel,
     * Glue, Ink or Raddi tab every row is filed under that tab (Diesel, Glue and Ink rows also take quantity × rate) and the item name is kept on the line.
     * Empty rows the form always shows at the end are dropped.
     */
    protected function prepareForValidation(): void
    {
        OtherOption::resolve($this, 'expense_head_id', ExpenseHead::class);

        // Ink and Glue: the supplier is typed or picked; a new name is added to Parties as a supplier.
        $supplier = trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('supplier')));
        if ($supplier !== '') {
            $party = OtherOption::findOrCreate(Party::class, $supplier, ['kind' => PartyKind::Supplier, 'is_active' => true]);
            $this->merge(['supplier' => $party->name, 'paid_to' => $party->name]);
        }

        $category = InventoryCategory::query()->where('show_as_tab', true)->find($this->integer('inventory_category_id'));
        $materialHead = $category ? OtherOption::findOrCreate(ExpenseHead::class, $category->name)->getKey() : null;
        $byQuantity = (bool) $category?->usesQuantity();

        $items = collect($this->input('items', []))
            ->filter(fn ($row) => is_array($row) && collect(['item', 'amount', 'paid_to', 'quantity', 'rate'])->contains(fn ($f) => trim((string) ($row[$f] ?? '')) !== ''))
            ->values();
        // An older single-payment form posts one head, amount and reason without rows.
        if ($items->isEmpty() && ($this->filled('amount') || $this->filled('reason'))) {
            $items = collect([['expense_head_id' => $this->input('expense_head_id'), 'item' => $this->input('reason'), 'amount' => $this->input('amount'), 'paid_to' => $this->input('paid_to')]]);
        }

        $items = $items->map(function (array $row) use ($materialHead, $byQuantity): array {
            $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($row['item'] ?? '')));
            $headId = $row['expense_head_id'] ?? null;

            if ($materialHead) {
                $headId = $materialHead;
            } elseif ($headId === OtherOption::Value) {
                $typed = trim((string) ($row['head_other'] ?? ''));
                $headId = $typed === '' ? null : OtherOption::findOrCreate(ExpenseHead::class, $typed)->getKey();
            } elseif (! $headId && $name !== '') {
                $headId = OtherOption::findOrCreate(ExpenseHead::class, $name)->getKey();
            }
            $headId = $headId ?: $this->input('expense_head_id');

            return [
                'expense_head_id' => $headId,
                'item' => $name !== '' ? $name : null,
                'description' => $name !== '' ? $name : ($row['description'] ?? null),
                'quantity' => $byQuantity ? ($row['quantity'] ?? null) : null,
                'rate' => $byQuantity ? ($row['rate'] ?? null) : null,
                'paid_to' => trim((string) ($row['paid_to'] ?? '')) ?: (trim((string) $this->input('paid_to')) ?: null),
                // On the Diesel, Glue and Ink tabs the line total is worked out from quantity × rate.
                'amount' => $byQuantity && is_numeric($row['quantity'] ?? null) && is_numeric($row['rate'] ?? null)
                    ? (int) round((float) $row['quantity'] * (float) $row['rate'])
                    : ($row['amount'] ?? null),
            ];
        });

        $this->merge(['items' => $items->all()]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items.*.expense_head_id' => __('item'), 'items.*.item' => __('item'), 'items.*.quantity' => __('quantity'), 'items.*.rate' => __('price per quantity'),
            'items.*.paid_to' => __('paid to'), 'items.*.amount' => __('price'),
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $byQuantity = (bool) InventoryCategory::query()->where('show_as_tab', true)->find($this->integer('inventory_category_id'))?->usesQuantity();

        return [
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'inventory_category_id' => ['nullable', Rule::exists('inventory_categories', 'id')->where('show_as_tab', true)->where(fn ($q) => $q->whereRaw('LOWER(name) != ?', ['raddi']))],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.expense_head_id' => ['required', Rule::exists('expense_heads', 'id')->where('is_active', true)],
            'items.*.item' => ['nullable', 'string', 'max:120'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', Rule::requiredIf($byQuantity), 'numeric', 'min:0.01', 'max:99999999'],
            'items.*.rate' => ['nullable', Rule::requiredIf($byQuantity), 'numeric', 'min:0', 'max:99999999'],
            'items.*.paid_to' => ['nullable', 'string', 'max:120'],
            'items.*.amount' => ['required', 'integer', 'min:1'],
            'supplier' => ['nullable', Rule::requiredIf((bool) InventoryCategory::query()->where('show_as_tab', true)->find($this->integer('inventory_category_id'))?->addsStock()), 'string', 'max:120'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ];
    }
}
