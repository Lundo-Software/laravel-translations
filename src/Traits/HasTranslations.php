<?php

declare(strict_types=1);

namespace Lundo\Translations\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasTranslations
{
    protected array $pendingTranslations = [];

    protected static function bootHasTranslations(): void
    {
        static::saved(function (self $model): void {
            $model->persistPendingTranslations();
        });
    }

    public function translations(): MorphMany
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $translationModel */
        $translationModel = config('translations.model', \Lundo\Translations\Models\Translation::class);

        return $this->morphMany($translationModel, 'translatable');
    }

    // --- Read ---

    public function getAttribute($key): mixed
    {
        if ($this->isTranslatableKey($key)) {
            $translated = $this->resolveTranslation($key, app()->getLocale());

            if ($translated !== null) {
                return $translated;
            }
        }

        return parent::getAttribute($key);
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();
        $locale = app()->getLocale();

        foreach ($this->translatable ?? [] as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $translated = $this->resolveTranslation($key, $locale);

            if ($translated !== null) {
                $attributes[$key] = $translated;
            }
        }

        return $attributes;
    }

    // --- Write ---

    public function setAttribute($key, $value): mixed
    {
        if ($this->isTranslatableKey($key) && ! $this->isDefaultLocale()) {
            $this->pendingTranslations[app()->getLocale()][$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    // Explicit write bypassing locale middleware (e.g. admin saving multiple languages at once)
    public function setTranslation(string $key, string $locale, ?string $value): static
    {
        if ($locale === config('translations.default_locale', 'nl')) {
            parent::setAttribute($key, $value);
            // Persist pending translations first to avoid save() flushing them as a side effect
            $this->persistPendingTranslations();
            $this->save();

            return $this;
        }

        $this->translations()->updateOrCreate(
            ['locale' => $locale, 'key' => $key],
            ['value' => $this->castForStorage($key, $value)],
        );

        $this->unsetRelation('translations');

        return $this;
    }

    public function getTranslation(string $key, string $locale): mixed
    {
        // Default locale values live in model columns, not the translations table
        if ($locale === config('translations.default_locale', 'nl')) {
            return parent::getAttribute($key);
        }

        $raw = $this->translations()
            ->where('locale', $locale)
            ->where('key', $key)
            ->value('value');

        return $this->castFromStorage($key, $raw);
    }

    /** Returns all translations for a key as ['locale' => 'value'], including the default locale. */
    public function getTranslations(string $key): array
    {
        $default = config('translations.default_locale', 'nl');

        $rows = $this->translations()
            ->where('key', $key)
            ->pluck('value', 'locale')
            ->all();

        $rows[$default] ??= parent::getAttribute($key);

        return $rows;
    }

    /** Sets translations for multiple locales at once: ['en' => 'Hello', 'nl' => 'Hallo']. */
    public function setTranslations(string $key, array $translations): static
    {
        foreach ($translations as $locale => $value) {
            $this->setTranslation($key, $locale, $value);
        }

        return $this;
    }

    public function hasTranslation(string $key, string $locale): bool
    {
        if ($locale === config('translations.default_locale', 'nl')) {
            return parent::getAttribute($key) !== null;
        }

        return $this->translations()
            ->where('locale', $locale)
            ->where('key', $key)
            ->exists();
    }

    public function forgetTranslation(string $key, string $locale): static
    {
        $this->translations()
            ->where('locale', $locale)
            ->where('key', $key)
            ->delete();

        $this->unsetRelation('translations');

        return $this;
    }

    public function forgetAllTranslations(string $locale): static
    {
        $this->translations()
            ->where('locale', $locale)
            ->delete();

        $this->unsetRelation('translations');

        return $this;
    }

    /** Returns all locales this model has a translation for, including the default locale if columns are set. */
    public function locales(): array
    {
        $default = config('translations.default_locale', 'nl');

        $locales = $this->translations()
            ->distinct()
            ->pluck('locale')
            ->all();

        $hasDefaultValue = collect($this->translatable ?? [])
            ->contains(fn ($key) => parent::getAttribute($key) !== null);

        if ($hasDefaultValue && ! in_array($default, $locales, true)) {
            $locales[] = $default;
        }

        return $locales;
    }

    // --- Media (requires spatie/laravel-medialibrary) ---

    /**
     * Returns media for the active locale, falling back to the fallback locale,
     * then to untagged (legacy) media. Requires locale custom_property on media.
     */
    public function getLocalizedMedia(string $collection, ?string $locale = null): Collection
    {
        $locale ??= app()->getLocale();
        $fallback = config('translations.fallback_locale', 'nl');

        return $this->getMedia($collection)
            ->filter(fn ($m) => $m->getCustomProperty('locale') === $locale)
            ->whenEmpty(fn () => $this->getMedia($collection)
                ->filter(fn ($m) => $m->getCustomProperty('locale') === $fallback))
            ->whenEmpty(fn () => $this->getMedia($collection)
                ->filter(fn ($m) => ! $m->getCustomProperty('locale')));
    }

    // --- Internals ---

    protected function resolveTranslation(string $key, string $locale): mixed
    {
        // Default locale values live in model columns, not the translations table
        if ($locale === config('translations.default_locale', 'nl')) {
            return parent::getAttribute($key);
        }

        $raw = $this->translations
            ->where('locale', $locale)
            ->where('key', $key)
            ->first()
            ?->value;

        return $this->castFromStorage($key, $raw);
    }

    protected function persistPendingTranslations(): void
    {
        foreach ($this->pendingTranslations as $locale => $keys) {
            foreach ($keys as $key => $value) {
                $this->translations()->updateOrCreate(
                    ['locale' => $locale, 'key' => $key],
                    ['value' => $this->castForStorage($key, $value)],
                );
            }
        }

        $this->pendingTranslations = [];
        $this->unsetRelation('translations');
    }

    protected function castForStorage(string $key, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Backed enums store their scalar value; unit enums store their name
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    protected function castFromStorage(string $key, ?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // Delegate to Eloquent's own casting pipeline (handles enums, encrypted, dates, custom casts, etc.)
        return $this->hasCast($key) ? $this->castAttribute($key, $value) : $value;
    }

    protected function isTranslatableKey(string $key): bool
    {
        return in_array($key, $this->translatable ?? [], true);
    }

    protected function isDefaultLocale(): bool
    {
        return app()->getLocale() === config('translations.default_locale', 'nl');
    }
}
