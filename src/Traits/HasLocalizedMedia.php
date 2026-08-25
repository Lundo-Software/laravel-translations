<?php

declare(strict_types=1);

namespace Lundo\Translations\Traits;

use Illuminate\Support\Collection;

trait HasLocalizedMedia
{
    /** Returns media for the active locale, falling back to the fallback locale, then to untagged (legacy) media. */
    public function getLocalizedMedia(string $collection, ?string $locale = null): Collection
    {
        $locale ??= app()->getLocale();
        $fallback = config('translations.fallback_locale', 'nl');
        $all = $this->getMedia($collection);

        $exact = $all->filter(fn ($m) => $m->getCustomProperty('locale') === $locale);
        if ($exact->isNotEmpty()) {
            return $exact;
        }

        if ($locale !== $fallback) {
            $fallbackMedia = $all->filter(fn ($m) => $m->getCustomProperty('locale') === $fallback);
            if ($fallbackMedia->isNotEmpty()) {
                return $fallbackMedia;
            }
        }

        return $all->filter(fn ($m) => ! $m->getCustomProperty('locale'));
    }

    /**
     * Returns media for exactly the given locale.
     * For the fallback locale, also accepts untagged (legacy) media.
     * No cross-locale fallback — use this when building admin locale maps.
     */
    public function getMediaForLocale(string $collection, string $locale): Collection
    {
        $fallback = config('translations.fallback_locale', 'nl');

        $result = $this->getMedia($collection)
            ->filter(fn ($m) => $m->getCustomProperty('locale') === $locale);

        if ($result->isEmpty() && $locale === $fallback) {
            $result = $this->getMedia($collection)
                ->filter(fn ($m) => ! $m->getCustomProperty('locale'));
        }

        return $result;
    }
}
