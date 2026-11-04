<?php

namespace App\Http\Requests;

use App\Enums\PartyKind;
use App\Models\InventoryItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'issued_on' => ['required', 'date'],
            'inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'issued_to' => ['required_without_all:employee_id,party_id', 'nullable', 'string', 'max:120'],
            'employee_id' => ['nullable', Rule::exists('employees', 'id')->where('is_active', true)],
            // Machine parts go to a kharadia (lathe turner) instead of an employee.
            'party_id' => ['nullable', Rule::exists('parties', 'id')->where('kind', PartyKind::Kharadia->value)],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'reference' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Items cannot leave inventory if their category keeps bills only or the balance does not cover them.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $item = InventoryItem::query()->with('category')->withStock()->find($this->integer('inventory_item_id'));

                if ($item->category->for_machines && ! $this->filled('party_id')) {
                    $validator->errors()->add('party_id', __('Choose the kharadia this machine part is given to.'));

                    return;
                }

                if (! $item->category->tracks_stock) {
                    $validator->errors()->add('inventory_item_id', __('This item\'s category keeps bills only, so it cannot be moved out.'));

                    return;
                }

                if ((float) $this->input('quantity') > $item->balance()) {
                    $validator->errors()->add('quantity', __('Only :n :unit in stock.', ['n' => InventoryItem::qty($item->balance()), 'unit' => $item->unit]));
                }
            },
        ];
    }
}
