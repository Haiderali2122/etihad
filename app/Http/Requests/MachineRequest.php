<?php

namespace App\Http\Requests;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MachineRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20', Rule::unique('machines', 'code')->ignore($this->route('machine'))],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(MachineType::class)],
            'model' => ['nullable', 'string', 'max:120'],
            'purchase_price' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(MachineStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
