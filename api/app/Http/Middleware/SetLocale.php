<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['fr', 'en'];

    private const DEFAULT_LOCALE = 'fr';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('X-Locale') ?? $request->getPreferredLanguage(self::SUPPORTED_LOCALES);

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = self::DEFAULT_LOCALE;
        }

        App::setLocale($locale);

        return $next($request);
    }
}
