<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BookRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150', Rule::unique('books', 'name')->ignore($this->route('book'))],
            'name_ur' => ['nullable', 'string', 'max:150'],
            'class_subject' => ['nullable', 'string', 'max:80'],
            'party_id' => ['nullable', 'exists:parties,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'formats' => ['required', 'array', 'min:1'],
            'formats.*.id' => ['nullable', 'integer'],
            'formats.*.name' => ['required', 'string', 'max:80', 'distinct'],
            'formats.*.size' => ['nullable', 'string', 'max:40'],
            'formats.*.forms' => ['required', 'numeric', 'min:0.5', 'max:999'],
            'formats.*.pages' => ['nullable', 'integer', 'min:1'],
            'formats.*.paper_type_id' => ['nullable', 'exists:paper_types,id'],
            'formats.*.folding_rate' => ['nullable', 'numeric', 'min:0'],
            'formats.*.binding_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'formats.*.name' => __('format name'),
            'formats.*.forms' => __('forms'),
            'formats.*.pages' => __('pages'),
            'formats.*.folding_rate' => __('folding rate'),
            'formats.*.binding_rate' => __('binding rate'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'formats.required' => __('Add at least one format.'),
        ];
    }

    /**
     * A format that work orders use cannot be removed from the book.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Book|null $book */
                $book = $this->route('book');

                if (! $book || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $kept = collect($this->input('formats'))->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
                $inUse = $book->formats()->whereNotIn('id', $kept)->has('workOrders')->pluck('name');

                if ($inUse->isNotEmpty()) {
                    $validator->errors()->add('formats', __('Work orders use :formats, so it cannot be removed.', ['formats' => $inUse->join(', ')]));
                }
            },
        ];
    }
}
