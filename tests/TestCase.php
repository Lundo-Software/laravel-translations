<?php

declare(strict_types=1);

namespace Lundo\Translations\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lundo\Translations\TranslationsServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [TranslationsServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('test_subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('sub_title')->nullable();
            $table->text('intro')->nullable();
            $table->timestamps();
        });
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('translations.default_locale', 'nl');
        $app['config']->set('translations.fallback_locale', 'nl');
    }
}
