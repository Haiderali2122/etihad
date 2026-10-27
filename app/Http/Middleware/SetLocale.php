<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Use the language chosen in the top bar (English or Urdu).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', $request->user()?->locale ?? config('app.locale'));

        app()->setLocale(in_array($locale, ['en', 'ur'], true) ? $locale : 'en');

        return $next($request);
    }
}
