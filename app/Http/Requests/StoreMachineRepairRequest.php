<?php

namespace App\Http\Requests;

use App\Enums\RepairKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMachineRepairRequest extends FormRequest
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
            'repaired_on' => ['required', 'date'],
            'machine_id' => ['required', 'exists:machines,id'],
            'part_id' => ['nullable', 'exists:parts,id'],
            'party_id' => ['required', 'exists:parties,id'],
            'kind' => ['required', Rule::enum(RepairKind::class)],
            'description' => ['required', 'string', 'max:255'],
            'bill_amount' => ['required', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0', 'lte:bill_amount'],
            'amount_paid' => ['required', 'integer', 'min:0'],
            'invoice_no' => ['nullable', 'string', 'max:40'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ];
    }
}
