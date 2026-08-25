<?php

declare(strict_types=1);

namespace Lundo\Translations\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTranslations
{
    protected array $pendingTranslations = [];

    protected ?int $translationSourceId = null;

    protected ?string $translationSourceType = null;

    protected static function bootHasTranslations(): void
    {
        static::saved(function (self $model): void {
            $model->persistPendingTranslations();

            if ($model->translationSourceId !== null) {
                $model->copyTranslationsFrom($model->translationSourceId, $model->translationSourceType ?? get_class($model));
                $model->translationSourceId = null;
                $model->translationSourceType = null;
            }
        });
    }

    public function replicate(?array $except = null): static
    {
        $replica = parent::replicate($except);
        $replica->translationSourceId = $this->id;
        $replica->translationSourceType = get_class($this);

        return $replica;
    }

    protected function copyTranslationsFrom(int $sourceId, string $sourceType): void
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $translationModel */
        $translationModel = config('translations.model', \Lundo\Translations\Models\Translation::class);

        $translationModel::where('translatable_type', $sourceType)
            ->where('translatable_id', $sourceId)
            ->each(function ($t) {
                $this->translations()->updateOrCreate(
                    ['locale' => $t->locale, 'key' => $t->key],
                    ['value' => $t->value],
                );
            });

        $this->unsetRelation('translations');
    }

    public function translations(): MorphMany
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $translationModel */
        $translationModel = config('translations.model', \Lundo\Translations\Models\Translation::class);

        return $this->morphMany($translationModel, 'translatable');
    }

    // --- Read ---

    public function getTranslatableAttributes(): array
    {
        return $this->translatable ?? [];
    }

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
        if ($this->isTranslatableKey($key) && is_array($value) && $this->isLocaleMap($value)) {
            $default = config('translations.default_locale', 'nl');

            if (array_key_exists($default, $value)) {
                parent::setAttribute($key, $value[$default]);
            }

            foreach ($value as $locale => $v) {
                if ($locale !== $default) {
                    $this->pendingTranslations[$locale][$key] = $v;
                }
            }

            return $this;
        }

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

    /** Returns all translations for a key as ['locale' => value|null] for every configured locale. */
    public function getTranslations(string $key): array
    {
        $default = config('translations.default_locale', 'nl');
        $locales = config('translations.locales', [$default]);

        $rows = $this->translations()
            ->where('key', $key)
            ->pluck('value', 'locale')
            ->map(fn ($raw) => $this->castFromStorage($key, $raw))
            ->all();

        $rows[$default] = parent::getAttribute($key);

        // Fill null for every configured locale that has no translation row
        foreach ($locales as $locale) {
            $rows[$locale] ??= null;
        }

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

        $value = $this->castFromStorage($key, $raw);

        // Treat all-empty arrays/objects as absent so getAttribute falls back to default locale
        return $this->isEffectivelyEmpty($value) ? null : $value;
    }

    protected function isEffectivelyEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        // Array of objects (e.g. options, sub_questions): empty when every item's text is blank
        if (is_array($value) && isset($value[0]) && is_array($value[0])) {
            return collect($value)->every(fn ($item) => empty(trim((string) ($item['text'] ?? ''))));
        }

        // Associative object (e.g. scale_labels): empty when all string values are blank
        if (is_array($value) && ! array_is_list($value)) {
            return collect($value)->every(fn ($v) => is_string($v) && trim($v) === '');
        }

        return false;
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

    /** Returns true when every key in $value is a configured locale (and the array is non-empty). */
    protected function isLocaleMap(array $value): bool
    {
        if (empty($value)) {
            return false;
        }

        $locales = config('translations.locales', [config('translations.default_locale', 'nl')]);

        foreach (array_keys($value) as $k) {
            if (! in_array($k, $locales, true)) {
                return false;
            }
        }

        return true;
    }
}
