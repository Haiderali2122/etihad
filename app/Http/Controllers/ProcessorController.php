<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Models\Party;
use App\Models\PlateJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Plate processors, kept apart from the other parties. Their plates and payments are the same plate records
 * shown on the Plates tab, so nothing is counted twice.
 */
class ProcessorController extends Controller
{
    public function index(): View
    {
        $processors = Party::query()->where('kind', PartyKind::PlateProcessor)->orderByDesc('is_active')->orderBy('name')->get();
        $totals = PlateJob::query()->whereIn('party_id', $processors->pluck('id'))
            ->selectRaw('party_id, SUM(plates) as given, SUM(returned_plates) as returned, SUM(amount) as billed')
            ->selectRaw('SUM(CASE WHEN paid_on IS NOT NULL THEN amount ELSE 0 END) as paid')
            ->groupBy('party_id')->get()->keyBy('party_id');

        return view('processors.index', ['processors' => $processors, 'totals' => $totals]);
    }

    public function show(Party $processor): View
    {
        abort_unless($processor->kind === PartyKind::PlateProcessor, 404);
        $jobs = PlateJob::query()->with(['processor', 'workOrder'])->where('party_id', $processor->id)->latest('job_on')->latest('id')->get();

        return view('processors.show', [
            'processor' => $processor,
            'jobs' => $jobs,
            'given' => (int) $jobs->sum('plates'),
            'returned' => (int) $jobs->sum('returned_plates'),
            'billed' => (int) $jobs->sum('amount'),
            'paid' => (int) $jobs->whereNotNull('paid_on')->sum('amount'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('processors.form', [
            'processor' => new Party(['kind' => PartyKind::PlateProcessor, 'is_active' => true]),
            'returnTo' => $this->safeReturnUrl($request->query('return')) ?? route('processors.index'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $processor = Party::create($this->validated($request) + ['kind' => PartyKind::PlateProcessor]);

        return redirect($this->safeReturnUrl($request->input('return_to')) ?? route('processors.show', $processor))
            ->with('status', __('Processor :name saved.', ['name' => $processor->name]));
    }

    public function edit(Party $processor): View
    {
        abort_unless($processor->kind === PartyKind::PlateProcessor, 404);

        return view('processors.form', ['processor' => $processor, 'returnTo' => route('processors.show', $processor)]);
    }

    public function update(Request $request, Party $processor): RedirectResponse
    {
        abort_unless($processor->kind === PartyKind::PlateProcessor, 404);
        $processor->update($this->validated($request));

        return redirect()->route('processors.show', $processor)->with('status', __('Processor :name saved.', ['name' => $processor->name]));
    }

    /**
     * A processor with plate records is kept for the history; switch it off instead.
     */
    public function destroy(Party $processor): RedirectResponse
    {
        abort_unless($processor->kind === PartyKind::PlateProcessor, 404);

        if (PlateJob::query()->where('party_id', $processor->id)->exists()) {
            return back()->withErrors(['processor' => __(':name has plate records, so it cannot be deleted. Untick Active instead.', ['name' => $processor->name])]);
        }

        $processor->delete();

        return redirect()->route('processors.index')->with('status', __('Processor deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
