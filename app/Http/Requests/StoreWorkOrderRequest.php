<?php

namespace App\Http\Requests;

use App\Enums\PartyKind;
use App\Enums\PrintMode;
use App\Models\BookFormat;
use App\Models\PaperType;
use App\Models\WorkOrder;
use App\Support\PaperStock;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkOrderRequest extends FormRequest
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
            'ordered_on' => ['required', 'date'],
            'book_format_id' => ['required', 'exists:book_formats,id'],
            'copies' => ['required', 'integer', 'min:1'],
            'wastage_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'paper_type_id' => ['required', 'exists:paper_types,id'],
            'party_id' => ['required', 'exists:parties,id'],
            'mode' => ['required', Rule::enum(PrintMode::class)],
            'machine_id' => ['nullable', 'required_if:mode,in_house', 'exists:machines,id'],
            'press_id' => ['nullable', 'required_if:mode,outsource', Rule::exists('parties', 'id')->where('kind', PartyKind::Press->value)],
            // Outside printing is charged per rim; Younus takes the charge to the press, so it goes in his khata.
            'printing_rate' => ['nullable', 'required_if:mode,outsource', 'numeric', 'min:0', 'max:100000'],
            'buyer_id' => ['nullable', Rule::exists('parties', 'id')->where('kind', PartyKind::Buyer->value)],
            'due_on' => ['nullable', 'date', 'after_or_equal:ordered_on'],
            'note' => ['nullable', 'string', 'max:255'],
            // "paper type id-party id" of the stock the shortage is taken from.
            'shortage_stock' => ['nullable', 'regex:/^\d+-\d+$/'],
        ];
    }

    /**
     * The godown must hold enough of the chosen paper for the rims this order needs.
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

                $stock = app(PaperStock::class);
                $needed = $this->rimsNeeded();
                $available = $stock->available($this->integer('paper_type_id'), $this->integer('party_id'));
                $short = $needed - $available;
                if ($short <= 0) {
                    return;
                }

                // Short: the rest can come from another stock; the order's party then owes it (Paper owed).
                if (! $this->filled('shortage_stock')) {
                    $validator->errors()->add('paper_type_id', __('This order needs :need rims but only :n are in stock for this paper and party.', [
                        'need' => number_format($needed), 'n' => number_format($available),
                    ]));

                    return;
                }
                [$coverType, $coverParty] = $this->shortageStock();
                if ($coverType === $this->integer('paper_type_id') && $coverParty === $this->integer('party_id')) {
                    $validator->errors()->add('shortage_stock', __('Pick a different stock for the rest.'));

                    return;
                }
                $cover = $stock->available($coverType, $coverParty);
                if ($cover < $short) {
                    $validator->errors()->add('shortage_stock', __('That stock has only :n rims; :short are needed.', ['n' => number_format($cover), 'short' => number_format($short)]));
                }
            },
        ];
    }

    /**
     * The stock picked to cover a shortage: [paper type id, party id], or null when none.
     *
     * @return array{0: int, 1: int}|null
     */
    public function shortageStock(): ?array
    {
        $parts = explode('-', (string) $this->input('shortage_stock'));

        return $this->filled('shortage_stock') && count($parts) === 2 ? [(int) $parts[0], (int) $parts[1]] : null;
    }

    public function rimsNeeded(): int
    {
        $format = BookFormat::query()->findOrFail($this->integer('book_format_id'));
        $sheets = PaperType::query()->whereKey($this->integer('paper_type_id'))->value('sheets_per_unit') ?: 500;

        return WorkOrder::rims((float) $format->forms, $this->integer('copies'), (float) $this->input('wastage_percent'), $sheets)['needed'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['machine_id' => __('machine'), 'press_id' => __('outside press'), 'book_format_id' => __('book')];
    }
}
