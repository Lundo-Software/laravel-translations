<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Lundo\Translations\Middleware\SetLocaleFromUser;

it('sets locale from authenticated user preferred_locale', function (): void {
    $user = new class implements Authenticatable {
        public string $preferred_locale = 'en';
        public function getAuthIdentifierName(): string { return 'id'; }
        public function getAuthIdentifier(): mixed { return 1; }
        public function getAuthPasswordName(): string { return 'password'; }
        public function getAuthPassword(): string { return ''; }
        public function getRememberToken(): string { return ''; }
        public function setRememberToken($value): void {}
        public function getRememberTokenName(): string { return ''; }
    };

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    (new SetLocaleFromUser)->handle($request, fn () => response('ok'));

    expect(app()->getLocale())->toBe('en');
});

it('falls back to default locale when user has no preferred_locale', function (): void {
    $user = new class implements Authenticatable {
        public ?string $preferred_locale = null;
        public function getAuthIdentifierName(): string { return 'id'; }
        public function getAuthIdentifier(): mixed { return 1; }
        public function getAuthPasswordName(): string { return 'password'; }
        public function getAuthPassword(): string { return ''; }
        public function getRememberToken(): string { return ''; }
        public function setRememberToken($value): void {}
        public function getRememberTokenName(): string { return ''; }
    };

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    (new SetLocaleFromUser)->handle($request, fn () => response('ok'));

    expect(app()->getLocale())->toBe('nl');
});

it('falls back to default locale when no user is authenticated', function (): void {
    (new SetLocaleFromUser)->handle(Request::create('/'), fn () => response('ok'));

    expect(app()->getLocale())->toBe('nl');
});
