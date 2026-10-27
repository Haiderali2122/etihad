<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaperTypeRequest;
use App\Models\PaperType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaperTypeController extends Controller
{
    public function index(): View
    {
        return view('paper.types.index', ['paperTypes' => PaperType::query()->orderBy('code')->get()]);
    }

    public function create(): View
    {
        return view('paper.types.form', ['paperType' => new PaperType(['unit' => 'rims', 'sheets_per_unit' => 500, 'is_active' => true])]);
    }

    public function store(PaperTypeRequest $request): RedirectResponse
    {
        PaperType::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('paper-types.index')->with('status', __('Paper type saved.'));
    }

    public function edit(PaperType $paperType): View
    {
        return view('paper.types.form', ['paperType' => $paperType]);
    }

    public function update(PaperTypeRequest $request, PaperType $paperType): RedirectResponse
    {
        $paperType->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('paper-types.index')->with('status', __('Paper type saved.'));
    }
}
