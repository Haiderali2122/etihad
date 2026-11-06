<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every delete (any DELETE request) needs the "Delete records" permission. The reason is optional;
 * when given it is kept (trimmed) for the deletion log.
 */
class GuardDeletions
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('DELETE')) {
            abort_unless(Gate::allows('records.delete'), 403, __('You do not have permission to delete records.'));

            $note = trim((string) $request->input('delete_note'));
            $request->merge(['delete_note' => $note === '' ? null : mb_substr($note, 0, 255)]);
        }

        return $next($request);
    }
}
