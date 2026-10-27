<?php

namespace App\Http\Requests;

use App\Enums\PartyKind;
use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PartyRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(PartyKind::class)],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * A party with history keeps its kind, or its records would drop out of the screens filed under that kind.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Party|null $party */
                $party = $this->route('party');

                if (! $party || $validator->errors()->isNotEmpty() || $this->input('kind') === $party->kind->value) {
                    return;
                }

                if ($party->hasHistory()) {
                    $validator->errors()->add('kind', __('This party already has records as :kind, so its kind cannot be changed.', ['kind' => $party->kind->label()]));
                }
            },
        ];
    }
}
