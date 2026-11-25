<?php

namespace App\Http\Controllers;

use App\Models\Deletion;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The deletion log: every record anyone has deleted, with the reason and who did it. Read-only, for the owner only,
 * and not linked from the sidebar: it is opened by typing its address (/deletions).
 */
class DeletionController extends Controller
{
    public function index(Request $request): View
    {
        // The owner only (and only while their account is active, which the permission check covers).
        abort_unless($request->user()?->isOwner() && $request->user()->can('deletions.view'), 403);

        return view('deletions.index', [
            'deletions' => Deletion::query()->with('deleter')->latest('created_at')->latest('id')->paginate(40),
            'total' => Deletion::query()->count(),
        ]);
    }
}
