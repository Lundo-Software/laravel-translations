# laravel-translations

Transparent polymorphic translations for Eloquent models. The default locale is stored directly in model columns; all other locales are stored in a shared `translations` table — no JSON columns, no extra casts.

## Requirements

- PHP 8.3+
- Laravel 11 or 12

## Installation

```bash
composer require lundo/laravel-translations
```

Run the migration:

```bash
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=translations-config
```

## Setup

Add the `HasTranslations` trait to your model and define a `$translatable` array:

```php
use Lundo\Translations\Traits\HasTranslations;

class Article extends Model
{
    use HasTranslations;

    protected array $translatable = ['title', 'body'];
}
```

That's it. The default locale (`nl` by default) is read from and written to the model columns as normal. Non-default locales are transparently read from and written to the `translations` table.

## Reading translations

Accessing a translatable attribute always returns the value for the active locale, falling back to the model column if no translation exists:

```php
app()->setLocale('en');

$article->title; // returns English translation, or the NL column value if none exists
```

Get a specific locale explicitly:

```php
$article->getTranslation('title', 'en'); // null if not set
$article->getTranslations('title');      // ['nl' => 'Titel', 'en' => 'Title', 'fr' => '...']
```

Check existence:

```php
$article->hasTranslation('title', 'en'); // bool
```

Get all locales the model has a value for:

```php
$article->locales(); // ['nl', 'en', 'fr']
```

## Writing translations

Setting an attribute while a non-default locale is active buffers the value and persists it when the model is saved:

```php
app()->setLocale('en');

$article->title = 'English title';
$article->save(); // writes to translations table
```

Set a specific locale directly (bypasses the active locale):

```php
$article->setTranslation('title', 'en', 'English title');
$article->setTranslation('title', 'nl', 'Nederlandse titel'); // writes to model column
```

Set multiple locales at once:

```php
$article->setTranslations('title', [
    'nl' => 'Nederlandse titel',
    'en' => 'English title',
    'fr' => 'Titre français',
]);
```

## Removing translations

```php
$article->forgetTranslation('title', 'en');   // remove one key for a locale
$article->forgetAllTranslations('en');         // remove all keys for a locale
```

## Middleware

`SetLocaleFromUser` reads the authenticated user's preferred locale and sets it for the request. Register it in your route middleware:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Lundo\Translations\Middleware\SetLocaleFromUser::class);
})
```

The attribute it reads from is configurable:

```php
// config/translations.php
'user_locale_attribute' => 'preferred_locale',
```

## Performance scope

`WithActiveLocaleScope` is an opt-in global scope that eager-loads only the active locale's translations instead of all locales. Add it to a model when you have many locales and want to avoid loading all rows:

```php
use Lundo\Translations\Scopes\WithActiveLocaleScope;

protected static function booted(): void
{
    static::addGlobalScope(new WithActiveLocaleScope);
}
```

## Configuration

| Key                     | Default                            | Description                               |
| ----------------------- | ---------------------------------- | ----------------------------------------- |
| `default_locale`        | `env('APP_LOCALE', 'nl')`          | Locale stored in model columns            |
| `fallback_locale`       | `env('APP_FALLBACK_LOCALE', 'nl')` | Fallback when translation is missing      |
| `model`                 | `Translation::class`               | Eloquent model for the translations table |
| `user_locale_attribute` | `preferred_locale`                 | User attribute read by the middleware     |

## License

MIT
