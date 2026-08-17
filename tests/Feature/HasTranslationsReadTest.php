<?php

declare(strict_types=1);

use Lundo\Translations\Tests\Models\TestSubject;
use Lundo\Translations\Tests\TestCase;

uses(TestCase::class);

it('returns the model attribute when no translation exists', function (): void {
    app()->setLocale('nl');

    $subject = TestSubject::create(['name' => 'Nederlandse naam']);

    expect($subject->name)->toBe('Nederlandse naam');
});

it('returns the translation for a non-default locale', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);

    $subject->setTranslation('name', 'en', 'English name');

    app()->setLocale('en');
    $subject->refresh();

    expect($subject->name)->toBe('English name');
});

it('falls back to the model attribute when no translation exists for the locale', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);

    app()->setLocale('en');
    $subject->refresh();

    expect($subject->name)->toBe('Nederlandse naam');
});

it('returns translated values in attributesToArray', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam', 'sub_title' => 'Ondertitel']);

    $subject->setTranslation('name', 'en', 'English name');
    $subject->setTranslation('sub_title', 'en', 'Subtitle');

    app()->setLocale('en');
    $subject->refresh();

    $array = $subject->toArray();

    expect($array['name'])->toBe('English name')
        ->and($array['sub_title'])->toBe('Subtitle');
});

it('returns translated values when serialized to JSON', function (): void {
    app()->setLocale('nl');
    $subject = TestSubject::create(['name' => 'Nederlandse naam']);

    $subject->setTranslation('name', 'en', 'English name');

    app()->setLocale('en');
    $subject->refresh();

    expect(json_decode($subject->toJson(), true)['name'])->toBe('English name');
});
