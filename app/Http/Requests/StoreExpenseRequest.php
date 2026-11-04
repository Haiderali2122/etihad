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
     * "Other…" on the list: save the typed name to the list (or reuse a matching one) before validating.
     */
    protected function prepareForValidation(): void
    {
        OtherOption::resolve($this, 'expense_head_id', ExpenseHead::class);

        // Blank rows the form always shows are not items. A single amount and reason make a one-item receipt.
        $items = collect($this->input('items', []))
            ->filter(fn ($row) => is_array($row) && (trim((string) ($row['description'] ?? '')) !== '' || (string) ($row['amount'] ?? '') !== ''))
            ->values();
        if ($items->isEmpty() && ($this->filled('amount') || $this->filled('reason'))) {
            $items = collect([['description' => $this->input('reason'), 'amount' => $this->input('amount')]]);
        }
        $this->merge(['items' => $items->all()]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['items.*.description' => __('item'), 'items.*.amount' => __('amount')];
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
            'expense_head_id' => ['required', Rule::exists('expense_heads', 'id')->where('is_active', true)],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'inventory_category_id' => ['nullable', Rule::exists('inventory_categories', 'id')->where('show_as_tab', true)],
            'paid_to' => ['required', 'string', 'max:120'],
            // One line per thing paid for; the expense amount is their total.
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'integer', 'min:1'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ];
    }
}
