<?php

namespace App\Http\Controllers;

use App\Http\Requests\DesignationRequest;
use App\Models\Designation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DesignationController extends Controller
{
    public function index(): View
    {
        return view('designations.index', [
            'designations' => Designation::query()
                ->withCount(['employees', 'employees as active_employees_count' => fn ($q) => $q->active()])
                ->orderByDesc('is_active')->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('designations.form', ['designation' => new Designation(['is_active' => true])]);
    }

    public function store(DesignationRequest $request): RedirectResponse
    {
        $designation = Designation::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('designations.index')->with('status', __('Designation :name saved.', ['name' => $designation->name]));
    }

    public function edit(Designation $designation): View
    {
        return view('designations.form', ['designation' => $designation]);
    }

    /**
     * Renaming a designation renames it on every employee who holds it.
     */
    public function update(DesignationRequest $request, Designation $designation): RedirectResponse
    {
        DB::transaction(function () use ($request, $designation) {
            $oldName = $designation->name;
            $designation->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

            if ($designation->name !== $oldName) {
                DB::table('employees')->where('designation', $oldName)->update(['designation' => $designation->name, 'updated_at' => now()]);
            }
        });

        return redirect()->route('designations.index')->with('status', __('Designation :name saved.', ['name' => $designation->name]));
    }

    /**
     * A designation someone holds stays; it can be deactivated so it is no longer offered for new staff.
     */
    public function destroy(Designation $designation): RedirectResponse
    {
        if ($designation->employees()->exists()) {
            return back()->withErrors(['designation' => __(':name is given to staff. Change their designation first, or deactivate it instead.', ['name' => $designation->name])]);
        }

        $designation->delete();

        return redirect()->route('designations.index')->with('status', __('Designation :name deleted.', ['name' => $designation->name]));
    }
}
