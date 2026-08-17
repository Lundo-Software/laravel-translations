<?php

declare(strict_types=1);

namespace Lundo\Translations\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $attribute = config('translations.user_locale_attribute', 'preferred_locale');
        $fallback = config('translations.default_locale', 'nl');

        $locale = auth()->user()?->{$attribute} ?? $fallback;

        app()->setLocale($locale);

        return $next($request);
    }
}
