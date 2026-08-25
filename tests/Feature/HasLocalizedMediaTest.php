<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lundo\Translations\Traits\HasLocalizedMedia;

// Minimal fake media item — stands in for Spatie Media without requiring the package
function fakeMedia(?string $locale): object
{
    return new class($locale) {
        public function __construct(private readonly ?string $locale) {}

        public function getCustomProperty(string $key): mixed
        {
            return $key === 'locale' ? $this->locale : null;
        }
    };
}

// Stub model that fulfils the getMedia() contract HasLocalizedMedia depends on
function makeModel(array $mediaItems): Model
{
    $model = new class extends Model {
        use HasLocalizedMedia;

        public array $fakeMedia = [];

        /** @phpstan-ignore-next-line */
        public function getMedia(string $collection): Collection
        {
            return collect($this->fakeMedia);
        }
    };

    $model->fakeMedia = $mediaItems;

    return $model;
}

it('returns media matching the active locale', function (): void {
    app()->setLocale('en');

    $model = makeModel([
        fakeMedia('nl'),
        fakeMedia('en'),
    ]);

    $result = $model->getLocalizedMedia('photos');

    expect($result)->toHaveCount(1)
        ->and($result->first()->getCustomProperty('locale'))->toBe('en');
});

it('falls back to fallback locale when no active-locale media exists', function (): void {
    app()->setLocale('en');
    app('config')->set('translations.fallback_locale', 'nl');

    $model = makeModel([
        fakeMedia('nl'),
    ]);

    $result = $model->getLocalizedMedia('photos');

    expect($result)->toHaveCount(1)
        ->and($result->first()->getCustomProperty('locale'))->toBe('nl');
});

it('falls back to untagged media when neither locale nor fallback media exists', function (): void {
    app()->setLocale('en');
    app('config')->set('translations.fallback_locale', 'nl');

    $model = makeModel([
        fakeMedia(null),
        fakeMedia(null),
    ]);

    $result = $model->getLocalizedMedia('photos');

    expect($result)->toHaveCount(2);
});

it('prefers active locale over fallback and untagged', function (): void {
    app()->setLocale('en');
    app('config')->set('translations.fallback_locale', 'nl');

    $model = makeModel([
        fakeMedia('nl'),
        fakeMedia('en'),
        fakeMedia(null),
    ]);

    $result = $model->getLocalizedMedia('photos');

    expect($result)->toHaveCount(1)
        ->and($result->first()->getCustomProperty('locale'))->toBe('en');
});

it('prefers fallback locale over untagged', function (): void {
    app()->setLocale('en');
    app('config')->set('translations.fallback_locale', 'nl');

    $model = makeModel([
        fakeMedia('nl'),
        fakeMedia(null),
    ]);

    $result = $model->getLocalizedMedia('photos');

    expect($result)->toHaveCount(1)
        ->and($result->first()->getCustomProperty('locale'))->toBe('nl');
});

it('returns empty collection when no media exists at all', function (): void {
    app()->setLocale('en');
    app('config')->set('translations.fallback_locale', 'nl');

    $model = makeModel([]);

    $result = $model->getLocalizedMedia('photos');

    expect($result)->toBeEmpty();
});

it('accepts an explicit locale parameter instead of the active locale', function (): void {
    app()->setLocale('en');
    app('config')->set('translations.fallback_locale', 'nl');

    $model = makeModel([
        fakeMedia('fr'),
        fakeMedia('en'),
    ]);

    $result = $model->getLocalizedMedia('photos', 'fr');

    expect($result)->toHaveCount(1)
        ->and($result->first()->getCustomProperty('locale'))->toBe('fr');
});
