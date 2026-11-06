<?php

namespace App\Http\Requests;

use App\Enums\PartyKind;
use App\Models\Party;
use App\Support\OtherOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A supplier typed in under "Other…" is added to Parties as a supplier, or matched to an existing party.
     */
    protected function prepareForValidation(): void
    {
        OtherOption::resolve($this, 'party_id', Party::class, ['kind' => PartyKind::Supplier, 'is_active' => true]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchased_on' => ['required', 'date'],
            'party_id' => ['required', 'exists:parties,id'],
            'inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'rate' => ['required', 'numeric', 'min:0'],
            'bill_no' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ];
    }
}
