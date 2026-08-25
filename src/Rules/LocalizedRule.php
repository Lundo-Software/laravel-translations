<?php

declare(strict_types=1);

namespace Lundo\Translations\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a field is either a plain string (treated as the default locale)
 * or an associative array whose keys are all valid configured locales and whose
 * values are strings or null.
 *
 * Usage:
 *   Rule::localized()                          // uses config('translations.locales')
 *   Rule::localized(['nl', 'en'])              // explicit locale list
 */
class LocalizedRule implements ValidationRule
{
    /** @param list<string>|null $locales */
    public function __construct(private readonly ?array $locales = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || is_string($value)) {
            return;
        }

        if (! is_array($value)) {
            $fail('validation.localized')->translate();

            return;
        }

        $allowed = $this->locales ?? config('translations.locales', [config('translations.default_locale', 'nl')]);

        foreach ($value as $locale => $translation) {
            if (! in_array($locale, $allowed, true)) {
                $fail('validation.localized_unknown_locale')->translate(['locale' => $locale]);

                return;
            }

            if ($translation !== null && ! is_string($translation)) {
                $fail('validation.localized_invalid_value')->translate(['locale' => $locale]);

                return;
            }
        }
    }
}
