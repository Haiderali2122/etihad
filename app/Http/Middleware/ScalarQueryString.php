<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every filter in the app takes single values, so a hand-edited link such as ?kind[]=x is reduced to
 * nothing instead of reaching controllers that expect a string.
 */
class ScalarQueryString
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->query->all() as $key => $value) {
            if (is_array($value)) {
                $request->query->remove($key);
            }
        }

        return $next($request);
    }
}
