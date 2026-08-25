<?php

declare(strict_types=1);

return [
    /*
     * The locale stored in model columns (the "source" language).
     * Writes in this locale go directly to the model attribute, not the translations table.
     */
    'default_locale' => env('APP_LOCALE', 'nl'),

    /*
     * Fallback locale used when a translation is missing.
     */
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'nl'),

    /*
     * The Eloquent model used to store translations.
     */
    'model' => \Lundo\Translations\Models\Translation::class,

    /*
     * The attribute on the authenticated user that holds the preferred locale.
     * Used by SetLocaleFromUser middleware.
     */
    'user_locale_attribute' => 'preferred_locale',

    /*
     * All supported locales. getTranslations() returns null for any locale not yet translated.
     */
    'locales' => ['nl', 'en'],
];
