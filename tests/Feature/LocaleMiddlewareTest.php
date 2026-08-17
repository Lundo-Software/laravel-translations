<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Lundo\Translations\Middleware\SetLocaleFromUser;
use Lundo\Translations\Tests\TestCase;

uses(TestCase::class);

it('sets locale from authenticated user preferred_locale', function (): void {
    $user = new class {
        public string $preferred_locale = 'en';
    };

    $this->actingAs($user);

    $middleware = new SetLocaleFromUser;
    $middleware->handle(Request::create('/'), fn () => response('ok'));

    expect(app()->getLocale())->toBe('en');
});

it('falls back to default locale when user has no preferred_locale', function (): void {
    $user = new class {
        public ?string $preferred_locale = null;
    };

    $this->actingAs($user);

    $middleware = new SetLocaleFromUser;
    $middleware->handle(Request::create('/'), fn () => response('ok'));

    expect(app()->getLocale())->toBe('nl');
});

it('falls back to default locale when no user is authenticated', function (): void {
    $middleware = new SetLocaleFromUser;
    $middleware->handle(Request::create('/'), fn () => response('ok'));

    expect(app()->getLocale())->toBe('nl');
});
