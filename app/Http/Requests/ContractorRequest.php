<?php

namespace App\Http\Requests;

use App\Enums\ContractorWork;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'name_ur' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'work' => ['required', Rule::enum(ContractorWork::class)],
            'folding_rate' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'form_rate' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'copy_rate' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
