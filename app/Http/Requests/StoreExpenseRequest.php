<?php

namespace App\Http\Requests;

use App\Models\ExpenseHead;
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
     * Each line is its own payment: head, who was paid, what for, and the amount. A head picked as "Other…" is saved
     * to the list (or a matching one reused). A single head, payee, amount and reason make a one-line receipt.
     */
    protected function prepareForValidation(): void
    {
        OtherOption::resolve($this, 'expense_head_id', ExpenseHead::class);

        // Blank rows the form always shows are not payments.
        $items = collect($this->input('items', []))
            ->filter(fn ($row) => is_array($row) && collect(['description', 'amount', 'paid_to'])->contains(fn ($f) => trim((string) ($row[$f] ?? '')) !== ''))
            ->values();
        if ($items->isEmpty() && ($this->filled('amount') || $this->filled('reason'))) {
            $items = collect([['description' => $this->input('reason'), 'amount' => $this->input('amount')]]);
        }

        $items = $items->map(function (array $row) {
            if (($row['expense_head_id'] ?? null) === OtherOption::Value) {
                $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($row['head_other'] ?? '')));
                $row['expense_head_id'] = $name === '' ? null : OtherOption::findOrCreate(ExpenseHead::class, $name)->getKey();
            }

            // Lines without their own head or payee take the receipt's.
            return [
                'expense_head_id' => ($row['expense_head_id'] ?? null) ?: $this->input('expense_head_id'),
                'paid_to' => trim((string) ($row['paid_to'] ?? '')) ?: $this->input('paid_to'),
                'description' => $row['description'] ?? null,
                'amount' => $row['amount'] ?? null,
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
            'items.*.expense_head_id' => __('head'), 'items.*.paid_to' => __('paid to'),
            'items.*.description' => __('what for'), 'items.*.amount' => __('amount'),
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'inventory_category_id' => ['nullable', Rule::exists('inventory_categories', 'id')->where('show_as_tab', true)],
            // One line per payment; the expense amount is their total.
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.expense_head_id' => ['required', Rule::exists('expense_heads', 'id')->where('is_active', true)],
            'items.*.paid_to' => ['required', 'string', 'max:120'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.amount' => ['required', 'integer', 'min:1'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ];
    }
}
