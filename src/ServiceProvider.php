<?php

namespace SoluzioneSoftware\LaravelTranslations;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use SoluzioneSoftware\LaravelTranslations\Console\Commands\Locales;
use SoluzioneSoftware\LaravelTranslations\Console\Commands\Translations;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfig();
        $this->registerTranslationLoader();
        $this->registerTranslationsManager();
        $this->registerTranslator();
    }

    private function registerConfig()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/translations.php', 'translations'
        );
    }

    private function registerTranslationLoader()
    {
        $this->app->extend('translation.loader', function (Loader $service, $app) {
            return new FileLoader($service, $app['files'], $app['path.lang']);
        });
    }

    private function registerTranslationsManager()
    {
        $this->app->singleton(Manager::class, function ($app) {
            return new Manager($app['path.lang']);
        });
    }

    private function registerTranslator()
    {
        $this->app->extend('translator', function (TranslatorContract $service, $app) {
            return new Translator($service, $app['translation.loader']);
        });
    }

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();

            $this->publishes([
                __DIR__.'/../config/translations.php' => App::configPath('translations.php'),
            ], ['translations', 'config', 'translations-config']);
        }
    }

    private function registerCommands()
    {
        $this->commands([
            Locales::class,
            Translations::class,
        ]);
    }
}
