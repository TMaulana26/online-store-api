<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language');

        if ($locale) {
            // Get the primary language tag (e.g. "en-US,en;q=0.9" -> "en-us" -> "en")
            $locale = strtolower(trim(explode(',', $locale)[0]));
            $locale = explode('-', $locale)[0];

            $supportedLocales = ['en', 'id'];

            if (in_array($locale, $supportedLocales, true)) {
                app()->setLocale($locale);

                return $next($request);
            }
        }

        // Fallback to configured default application locale
        app()->setLocale(config('app.fallback_locale', 'en'));

        return $next($request);
    }
}
