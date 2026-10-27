<?php

namespace App\Http\Controllers;

use App\Enums\AdvanceKind;
use App\Http\Requests\StoreForemanSheetRequest;
use App\Models\Employee;
use App\Models\ForemanSheet;
use App\Models\SalaryAdvance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ForemanSheetController extends Controller
{
    public function create(Request $request): View
    {
        $foremen = Employee::query()->active()->where('is_foreman', true)->with(['team' => fn ($q) => $q->active()->orderBy('name')])->orderBy('name')->get();

        return view('foreman-sheets.create', [
            'foremen' => $foremen,
            'employees' => Employee::query()->active()->orderBy('name')->get(),
            'foremanId' => $request->integer('foreman') ?: $foremen->first()?->id,
        ]);
    }

    /**
     * Each handed-out line becomes that worker's advance; whatever is left stays as the foreman's own advance.
     */
    public function store(StoreForemanSheetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $sheet = DB::transaction(function () use ($data, $user): ForemanSheet {
            $sheet = ForemanSheet::create([
                'given_on' => $data['given_on'], 'foreman_id' => $data['foreman_id'], 'amount' => $data['amount'],
                'note' => $data['note'] ?? null, 'created_by' => $user->id,
            ]);

            $lines = collect($data['lines'] ?? [])->filter(fn ($l) => filled($l['employee_id'] ?? null) && (int) ($l['amount'] ?? 0) > 0);
            $handed = 0;
            foreach ($lines as $line) {
                $this->advance($sheet, (int) $line['employee_id'], (int) $line['amount'], $user->id);
                $handed += (int) $line['amount'];
            }

            if ($sheet->amount - $handed > 0) {
                $this->advance($sheet, $sheet->foreman_id, $sheet->amount - $handed, $user->id, __('Kept by the foreman'));
            }

            return $sheet;
        });

        return redirect()->route('foreman-sheets.show', $sheet)->with('status', $sheet->isPending()
            ? __('Foreman sheet saved and sent for approval.')
            : __('Foreman sheet saved.'));
    }

    public function show(ForemanSheet $foremanSheet): View
    {
        return view('foreman-sheets.show', ['sheet' => $foremanSheet->load(['foreman', 'advances.employee', 'creator', 'reviewer'])]);
    }

    private function advance(ForemanSheet $sheet, int $employeeId, int $amount, int $userId, ?string $note = null): void
    {
        SalaryAdvance::create([
            'employee_id' => $employeeId, 'given_on' => $sheet->given_on, 'amount' => $amount,
            'kind' => AdvanceKind::Foreman, 'foreman_sheet_id' => $sheet->id, 'note' => $note,
            'created_by' => $userId, 'status' => $sheet->status,
        ]);
    }
}
