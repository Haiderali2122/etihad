<?php

namespace App\Http\Requests;

use App\Enums\ContractorJobKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractorJobRequest extends FormRequest
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
            'contractor_id' => ['required', Rule::exists('contractors', 'id')->where('is_active', true)],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'kind' => ['required', Rule::enum(ContractorJobKind::class)],
            'job_on' => ['required', 'date'],
            'forms' => ['required', 'numeric', 'min:0.5'],
            'copies' => ['required', 'integer', 'min:1'],
            'folding_rate' => ['nullable', 'required_if:kind,folding', 'numeric', 'decimal:0,2', 'min:0'],
            'form_rate' => ['nullable', 'required_if:kind,binding', 'numeric', 'decimal:0,2', 'min:0'],
            'copy_rate' => ['nullable', 'required_if:kind,binding', 'numeric', 'decimal:0,2', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
