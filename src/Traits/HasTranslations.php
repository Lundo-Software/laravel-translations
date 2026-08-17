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
        $this->translations()->updateOrCreate(
            ['locale' => $locale, 'key' => $key],
            ['value' => $value],
        );

        $this->unsetRelation('translations');

        return $this;
    }

    public function getTranslation(string $key, string $locale): ?string
    {
        return $this->translations()
            ->where('locale', $locale)
            ->where('key', $key)
            ->value('value');
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

    protected function resolveTranslation(string $key, string $locale): ?string
    {
        // NL values live in model columns; only non-default locales are in the translations table
        return $this->translations
            ->where('locale', $locale)
            ->where('key', $key)
            ->first()
            ?->value;
    }

    protected function persistPendingTranslations(): void
    {
        foreach ($this->pendingTranslations as $locale => $keys) {
            foreach ($keys as $key => $value) {
                $this->translations()->updateOrCreate(
                    ['locale' => $locale, 'key' => $key],
                    ['value' => $value],
                );
            }
        }

        $this->pendingTranslations = [];
        $this->unsetRelation('translations');
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
