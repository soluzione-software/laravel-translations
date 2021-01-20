<?php

namespace SoluzioneSoftware\LaravelTranslations\Facades;

use Illuminate\Support\Facades\Facade;
use SoluzioneSoftware\LaravelTranslations\Manager;

/**
 * @method static array getLocales()
 * @method static addLocale(string $locale, bool $initialize = false)
 * @method static removeLocale(string $locale)
 * @method static array getTranslations(string $locale)
 * @method static string|null getTranslation(string $locale, string $key, string|null $namespace = null)
 * @method static updateTranslation(string $locale, string $key, string $value, string|null $namespace = null)
 * @method static removeTranslation(string $locale, string $key, string|null $namespace = null)
 *
 * @see Manager
 */
class Translations extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Manager::class;
    }
}
