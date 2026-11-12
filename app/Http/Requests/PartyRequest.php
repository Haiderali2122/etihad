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
     * A type saved before arrives as "custom:<name>"; "other" comes with a typed name. Both become kind "other"
     * with that name, matched to an earlier spelling (ignoring case and spaces) so the list has no duplicates.
     */
    protected function prepareForValidation(): void
    {
        $kind = (string) $this->input('kind');
        $typed = null;

        if (str_starts_with($kind, 'custom:')) {
            $typed = substr($kind, 7);
            $kind = PartyKind::Other->value;
        } elseif ($kind === PartyKind::Other->value) {
            $typed = (string) $this->input('kind_other');
        }

        $typed = trim((string) preg_replace('/\s+/u', ' ', (string) $typed));
        if ($typed !== '') {
            $typed = collect(Party::customTypes())->first(fn (string $t) => mb_strtolower($t) === mb_strtolower($typed)) ?? $typed;
        }

        $this->merge(['kind' => $kind, 'kind_other' => $kind === PartyKind::Other->value && $typed !== '' ? $typed : null]);
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
            'kind_other' => ['nullable', 'required_if:kind,'.PartyKind::Other->value, 'string', 'max:80'],
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
