<?php

namespace App\Http\Requests;

use App\Models\InventoryCategory;
use App\Support\OtherOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryItemRequest extends FormRequest
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
        OtherOption::resolve($this, 'inventory_category_id', InventoryCategory::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inventory_category_id' => ['required', 'exists:inventory_categories,id'],
            'name' => ['required', 'string', 'max:120',
                Rule::unique('inventory_items', 'name')->where('inventory_category_id', $this->input('inventory_category_id'))->ignore($this->route('inventory_item'))],
            'name_ur' => ['nullable', 'string', 'max:120'],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => __('This category already has an item with that name.')];
    }
}
