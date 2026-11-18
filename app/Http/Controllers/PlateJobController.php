<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Enums\WorkOrderStage;
use App\Models\Book;
use App\Models\Party;
use App\Models\PlateJob;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlateJobController extends Controller
{
    public function index(Request $request): View
    {
        $show = in_array($request->query('show'), ['unpaid', 'paid', 'all'], true) ? $request->query('show') : 'unpaid';

        return view('plate-jobs.index', [
            'jobs' => PlateJob::query()->with(['processor', 'workOrder', 'returns', 'payments'])
                ->when($show === 'unpaid', fn ($q) => $q->whereNull('paid_on'))
                ->when($show === 'paid', fn ($q) => $q->whereNotNull('paid_on'))
                ->latest('job_on')->latest('id')->paginate(30)->withQueryString(),
            'unpaidTotal' => (int) PlateJob::query()->whereNull('paid_on')->sum(DB::raw('amount - paid_amount')),
            'monthPlates' => (int) PlateJob::query()->where('job_on', '>=', now()->startOfMonth())->sum('plates'),
            'monthAmount' => (int) PlateJob::query()->where('job_on', '>=', now()->startOfMonth())->sum('amount'),
            'show' => $show,
        ]);
    }

    public function create(Request $request): View
    {
        return view('plate-jobs.create', [
            'lastRates' => $this->lastRates(),
            'sizes' => $this->sizes(),
            'books' => $this->books(),
            'processors' => Party::query()->active()->where('kind', PartyKind::PlateProcessor)->orderBy('name')->get(),
            'orders' => WorkOrder::query()->with('bookFormat.book')->where('stage', '!=', WorkOrderStage::Dispatched)->latest('id')->get(),
            'orderId' => $request->integer('order') ?: null,
            'back' => $request->integer('order') ? route('work-orders.show', $request->integer('order')) : route('plate-jobs.index'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->resolveTyped($request);
        $data = $request->validate($this->rules());

        $job = PlateJob::create($data + ['amount' => (int) round($data['plates'] * $data['rate']), 'created_by' => $request->user()->id]);

        return redirect($job->work_order_id ? route('work-orders.show', $job->work_order_id) : route('plate-jobs.index'))->with('status', __('Plate job saved.'));
    }

    /**
     * The same plate record is edited from the Plates and Processors tabs, so a change shows in both.
     */
    public function edit(Request $request, PlateJob $plateJob): View
    {
        return view('plate-jobs.create', [
            'job' => $plateJob->load(['returns', 'payments']),
            'sizes' => $this->sizes(),
            'books' => $this->books(),
            'processors' => Party::query()->where('kind', PartyKind::PlateProcessor)->where(fn ($q) => $q->where('is_active', true)->orWhereKey($plateJob->party_id))->orderBy('name')->get(),
            'orders' => WorkOrder::query()->with('bookFormat.book')->where(fn ($q) => $q->where('stage', '!=', WorkOrderStage::Dispatched)->orWhereKey($plateJob->work_order_id))->latest('id')->get(),
            'orderId' => $plateJob->work_order_id,
            'back' => $this->safeReturnUrl($request->query('return')) ?? route('plate-jobs.index', ['show' => 'all']),
        ]);
    }

    public function update(Request $request, PlateJob $plateJob): RedirectResponse
    {
        $this->resolveTyped($request);
        $data = $request->validate($this->rules());
        $amount = (int) round($data['plates'] * $data['rate']);
        if ($amount < $plateJob->paid_amount) {
            return back()->withInput()->withErrors(['rate' => __('Rs :paid has already been paid on this bill, so the amount cannot be less.', ['paid' => number_format($plateJob->paid_amount)])]);
        }

        $plateJob->update($data + ['amount' => (int) round($data['plates'] * $data['rate'])]);

        return redirect($this->safeReturnUrl($request->input('return_to')) ?? route('plate-jobs.index', ['show' => 'all']))->with('status', __('Plate job saved.'));
    }

    /**
     * Removes the plate record from the Plates and Processors tabs, and its payments from Cash-out.
     */
    public function destroy(PlateJob $plateJob): RedirectResponse
    {
        $plateJob->load(['processor', 'workOrder'])->delete();

        return back()->with('status', __('Plate job deleted.'));
    }

    /**
     * Plates the processor sent back.
     */
    public function returnPlates(Request $request, PlateJob $plateJob): RedirectResponse
    {
        $data = $request->validate([
            'returned_plates' => ['required', 'integer', 'min:1', 'max:'.$plateJob->platesHeld()],
            'returned_on' => ['required', 'date', 'after_or_equal:'.$plateJob->job_on->toDateString(), 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        // Each return is kept with its date; the job's returned total is their sum.
        $plateJob->returns()->create(['returned_on' => $data['returned_on'], 'plates' => $data['returned_plates'], 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id]);
        $plateJob->refresh();

        return back()->with('status', __(':n plates returned on :date; :held still with :name.', ['n' => $data['returned_plates'], 'date' => Carbon::parse($data['returned_on'])->format('j M'), 'held' => $plateJob->platesHeld(), 'name' => $plateJob->processor->name]));
    }

    /**
     * The processor and work order are typed or picked from the suggestions. A new processor name is added to
     * Processors; a work order is found by its number (it cannot be made from here).
     */
    private function resolveTyped(Request $request): void
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('processor_name')));
        if ($name !== '') {
            $processor = Party::query()->where('kind', PartyKind::PlateProcessor)->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->first()
                ?? Party::query()->create(['name' => $name, 'kind' => PartyKind::PlateProcessor, 'is_active' => true]);
            $request->merge(['party_id' => $processor->id]);
        }

        // Plates are made before the work order: the job records the book it is for, typed or picked.
        $book = trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('book_name')));
        if ($book === '' && $request->filled('work_order_id')) {
            $book = (string) WorkOrder::query()->find($request->integer('work_order_id'))?->bookName();
        }
        $request->merge(['book_name' => $book !== '' ? (Book::query()->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($book)])->value('name') ?? $book) : null]);
        $request->merge(['plate_size' => trim((string) $request->input('plate_size')) ?: null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'party_id' => ['required', Rule::exists('parties', 'id')->where('kind', PartyKind::PlateProcessor->value)],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'plate_size' => ['nullable', 'string', 'max:40'],
            'book_name' => ['nullable', 'string', 'max:150'],
            'job_on' => ['required', 'date'],
            'plates' => ['required', 'integer', 'min:1'],
            'rate' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The rate per plate on each processor's last job, for each plate size ("" = any size), keyed by lower-case
     * names, so a new job starts with it filled in.
     *
     * @return array<string, array<string, float>>
     */
    private function lastRates(): array
    {
        $rates = [];
        PlateJob::query()->with('processor:id,name')->orderBy('job_on')->orderBy('id')->get(['id', 'party_id', 'plate_size', 'rate'])
            ->each(function (PlateJob $job) use (&$rates): void {
                $name = mb_strtolower(trim((string) $job->processor?->name));
                $rates[$name][''] = (float) $job->rate;
                if ($job->plate_size) {
                    $rates[$name][mb_strtolower(trim($job->plate_size))] = (float) $job->rate;
                }
            });

        return $rates;
    }

    /**
     * Plate sizes used before, offered in the Plate size box.
     *
     * @return list<string>
     */
    private function sizes(): array
    {
        return PlateJob::query()->whereNotNull('plate_size')->distinct()->orderBy('plate_size')->pluck('plate_size')->all();
    }

    /**
     * Books offered in the "For which book" box: the books list and names typed on plate jobs before.
     *
     * @return list<string>
     */
    private function books(): array
    {
        return Book::query()->pluck('name')
            ->merge(PlateJob::query()->whereNotNull('book_name')->distinct()->pluck('book_name'))
            ->unique(fn ($n) => mb_strtolower(trim($n)))->sort()->values()->all();
    }
}
