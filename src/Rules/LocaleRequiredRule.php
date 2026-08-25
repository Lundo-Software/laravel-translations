<?php

declare(strict_types=1);

namespace Lundo\Translations\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Like LocalizedRule but additionally requires every configured locale to be present
 * with a non-null, non-empty-string value. Use on creation endpoints.
 *
 * Usage:
 *   Rule::localeRequired()                     // uses config('translations.locales')
 *   Rule::localeRequired(['nl', 'en'])         // explicit locale list
 */
class LocaleRequiredRule implements ValidationRule
{
    /** @param list<string>|null $locales */
    public function __construct(private readonly ?array $locales = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $required = $this->locales ?? config('translations.locales', [config('translations.default_locale', 'nl')]);

        if (! is_array($value)) {
            $fail('validation.locale_required')->translate();

            return;
        }

        foreach ($required as $locale) {
            if (! array_key_exists($locale, $value) || $value[$locale] === null || $value[$locale] === '') {
                $fail('validation.locale_required_missing')->translate(['locale' => $locale]);

                return;
            }

            if (! is_string($value[$locale])) {
                $fail('validation.localized_invalid_value')->translate(['locale' => $locale]);

                return;
            }
        }

        // Also reject unknown locale keys
        $allowed = $required;
        foreach (array_keys($value) as $locale) {
            if (! in_array($locale, $allowed, true)) {
                $fail('validation.localized_unknown_locale')->translate(['locale' => $locale]);

                return;
            }
        }
    }
}
