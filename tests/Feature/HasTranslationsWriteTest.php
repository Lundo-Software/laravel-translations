<?php

declare(strict_types=1);

use Lundo\Translations\Tests\Models\TestSubject;
use Lundo\Translations\Tests\TestCase;

uses(TestCase::class);

it('writes to model column when locale is default', function (): void {
    app()->setLocale('nl');

    $subject = TestSubject::create(['name' => 'Origineel']);
    $subject->update(['name' => 'Gewijzigd']);

    expect($subject->getRawOriginal('name'))->toBe('Gewijzigd')
        ->and($subject->translations()->count())->toBe(0);
});

it('does not overwrite model column when saving in a non-default locale', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);

    app()->setLocale('en');
    $subject->update(['name' => 'English name']);

    expect($subject->getRawOriginal('name'))->toBe('Nederlandse naam');
});

it('persists the translation when saving in a non-default locale', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);

    app()->setLocale('en');
    $subject->update(['name' => 'English name']);

    expect(
        $subject->translations()->where('locale', 'en')->where('key', 'name')->value('value')
    )->toBe('English name');
});

it('persists a translation via setTranslation without touching model columns', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);

    $subject->setTranslation('name', 'en', 'English name');

    expect($subject->getRawOriginal('name'))->toBe('Nederlandse naam')
        ->and($subject->getTranslation('name', 'en'))->toBe('English name');
});

it('updates an existing translation instead of inserting a duplicate', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);

    $subject->setTranslation('name', 'en', 'English name v1');
    $subject->setTranslation('name', 'en', 'English name v2');

    expect($subject->translations()->where('locale', 'en')->where('key', 'name')->count())->toBe(1)
        ->and($subject->getTranslation('name', 'en'))->toBe('English name v2');
});

it('handles create in non-default locale', function (): void {
    app()->setLocale('en');

    $subject = TestSubject::create(['name' => 'English name']);

    // Column should be empty — value was buffered as a translation
    expect($subject->getRawOriginal('name'))->toBeNull()
        ->and($subject->getTranslation('name', 'en'))->toBe('English name');
});
