<?php

declare(strict_types=1);

use Lundo\Translations\Tests\Models\TestSubject;

// --- getTranslations ---

it('getTranslations returns all locales including default', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);
    $subject->setTranslation('name', 'en', 'English name');
    $subject->setTranslation('name', 'fr', 'Nom français');

    $translations = $subject->getTranslations('name');

    expect($translations)->toBe([
        'en' => 'English name',
        'fr' => 'Nom français',
        'nl' => 'Nederlandse naam',
    ]);
});

it('getTranslations includes default locale from column even with no translation rows', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);

    expect($subject->getTranslations('name'))->toBe(['nl' => 'Nederlandse naam']);
});

// --- setTranslations ---

it('setTranslations sets multiple locales at once', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Origineel']);

    $subject->setTranslations('name', ['en' => 'English name', 'fr' => 'Nom français']);

    expect($subject->getTranslation('name', 'en'))->toBe('English name')
        ->and($subject->getTranslation('name', 'fr'))->toBe('Nom français');
});

it('setTranslations routes default locale to model column', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Origineel']);

    $subject->setTranslations('name', ['nl' => 'Gewijzigd', 'en' => 'Changed']);

    expect($subject->getRawOriginal('name'))->toBe('Gewijzigd')
        ->and($subject->translations()->where('locale', 'nl')->count())->toBe(0);
});

// --- hasTranslation ---

it('hasTranslation returns true for default locale when column is set', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);

    expect($subject->hasTranslation('name', 'nl'))->toBeTrue();
});

it('hasTranslation returns false for default locale when column is null', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create([]);

    expect($subject->hasTranslation('name', 'nl'))->toBeFalse();
});

it('hasTranslation returns true for non-default locale with a translation row', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);
    $subject->setTranslation('name', 'en', 'English name');

    expect($subject->hasTranslation('name', 'en'))->toBeTrue();
});

it('hasTranslation returns false for non-default locale without a translation row', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);

    expect($subject->hasTranslation('name', 'en'))->toBeFalse();
});

// --- forgetTranslation ---

it('forgetTranslation deletes a specific translation row', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);
    $subject->setTranslation('name', 'en', 'English name');

    $subject->forgetTranslation('name', 'en');

    expect($subject->hasTranslation('name', 'en'))->toBeFalse();
});

it('forgetTranslation does not affect other locales', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);
    $subject->setTranslation('name', 'en', 'English name');
    $subject->setTranslation('name', 'fr', 'Nom français');

    $subject->forgetTranslation('name', 'en');

    expect($subject->hasTranslation('name', 'fr'))->toBeTrue();
});

// --- forgetAllTranslations ---

it('forgetAllTranslations deletes all translation rows for a locale', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam', 'sub_title' => 'Ondertitel']);
    $subject->setTranslation('name', 'en', 'English name');
    $subject->setTranslation('sub_title', 'en', 'Subtitle');

    $subject->forgetAllTranslations('en');

    expect($subject->hasTranslation('name', 'en'))->toBeFalse()
        ->and($subject->hasTranslation('sub_title', 'en'))->toBeFalse();
});

// --- locales ---

it('locales returns all locales including the default when column is set', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);
    $subject->setTranslation('name', 'en', 'English name');
    $subject->setTranslation('name', 'fr', 'Nom français');

    expect($subject->locales())->toContain('nl', 'en', 'fr');
});

it('locales does not include default locale when all columns are null', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create([]);

    expect($subject->locales())->not->toContain('nl');
});
