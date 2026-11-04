<?php

namespace App\Http\Controllers;

use App\Models\Deletion;
use Illuminate\View\View;

/**
 * The deletion log: every record anyone has deleted, with the reason and who did it. Read-only.
 */
class DeletionController extends Controller
{
    public function index(): View
    {
        return view('deletions.index', [
            'deletions' => Deletion::query()->with('deleter')->latest('created_at')->latest('id')->paginate(40),
            'total' => Deletion::query()->count(),
        ]);
    }
}
