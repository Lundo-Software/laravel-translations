<?php

declare(strict_types=1);

namespace Lundo\Translations;

use Illuminate\Support\ServiceProvider;

class TranslationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/create_translations_table.php'
                => database_path('migrations/'.date('Y_m_d_His').'_create_translations_table.php'),
        ], 'translations-migrations');

        $this->publishes([
            __DIR__.'/../config/translations.php' => config_path('translations.php'),
        ], 'translations-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/translations.php', 'translations');
    }
}
