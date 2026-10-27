<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaperTypeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20', Rule::unique('paper_types', 'code')->ignore($this->route('paper_type'))],
            'name' => ['required', 'string', 'max:80'],
            'name_ur' => ['nullable', 'string', 'max:80'],
            'gsm' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'size' => ['required', 'string', 'max:40'],
            'unit' => ['required', 'in:rims,packets'],
            'sheets_per_unit' => ['required', 'integer', 'min:1'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
