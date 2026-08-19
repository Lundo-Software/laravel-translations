<?php

declare(strict_types=1);

namespace Lundo\Translations\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $attribute = config('translations.user_locale_attribute', 'preferred_locale');
        $fallback = config('translations.default_locale', 'nl');

        try {
            $locale = $request->user()?->{$attribute} ?? $fallback;
        } catch (\Throwable) {
            throw new Exception('Error retrieving user locale. Ensure the user model implements Illuminate\Contracts\Auth\Authenticatable and has the preferred locale attribute defined.');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
