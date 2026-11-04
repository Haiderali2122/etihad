<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every delete (any DELETE request) needs the "Delete records" permission and a reason. This checks both in
 * one place, so no delete button, existing or new, can skip the permission or the note.
 */
class GuardDeletions
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('DELETE')) {
            abort_unless(Gate::allows('records.delete'), 403, __('You do not have permission to delete records.'));

            $note = trim((string) $request->input('delete_note'));
            abort_if($note === '', 422, __('A reason is needed to delete this.'));

            $request->merge(['delete_note' => mb_substr($note, 0, 255)]);
        }

        return $next($request);
    }
}
