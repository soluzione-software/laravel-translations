<?php

namespace SoluzioneSoftware\LaravelTranslations;

use Illuminate\Container\Container;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class Manager
{
    /**
     * @var Filesystem
     */
    protected $disk;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var Loader
     */
    protected $translationsLoader;

    /**
     * Manager constructor.
     */
    public function __construct()
    {
        $this->disk = Storage::disk(Config::get('translations.disk'));
        $this->path = Config::get('translations.path');
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->translationsLoader = Container::getInstance()->make('translation.loader');
    }

    public function getTranslation(string $locale, string $key, ?string $namespace = null): ?string
    {
        if (!$this->hasLocale($locale)) {
            return null;
        }

        return Arr::get(
            $this->getTranslations($locale),
            $this->getKey($key, $namespace)
        );
    }

    protected function hasLocale(string $locale): bool
    {
        return in_array($locale, $this->getLocales());
    }

    public function getLocales(): array
    {
        return array_map(function (string $file) {
            return str_replace('.json', '', Arr::last(explode(DIRECTORY_SEPARATOR, $file)));
        }, $this->disk->allFiles($this->path));
    }

    /**
     * @param  string  $locale
     * @return array
     * @throws InvalidArgumentException if given locale does not exists
     * @noinspection PhpUnhandledExceptionInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function getTranslations(string $locale)
    {
        if (!$this->disk->exists($this->getPath($locale))) {
            throw new InvalidArgumentException("Locale $locale does not exists");
        }

        return json_decode($this->disk->get($this->getPath($locale)), true);
    }

    protected function getPath(string $locale): string
    {
        return $this->path.DIRECTORY_SEPARATOR."$locale.json";
    }

    protected function getKey(string $key, ?string $namespace): string
    {
        return ($namespace ? "$namespace::" : '').$key;
    }

    /**
     * @param  string  $locale
     * @param  bool  $initialize
     * @throws InvalidArgumentException if given locale already exists
     */
    public function addLocale(string $locale, bool $initialize = false)
    {
        if ($this->disk->exists($this->getPath($locale))) {
            throw new InvalidArgumentException("Locale $locale already exists");
        }

        $this->save($locale, $initialize ? $this->getDefaultTranslations($locale) : []);
    }

    protected function save(string $locale, array $translations)
    {
        $this->disk->put($this->getPath($locale), json_encode($translations));
    }

    public function getDefaultTranslations(string $locale): array
    {
        return array_merge(
            $this->getDefaultTranslationsForLocale(Config::get('app.fallback_locale')),
            $this->getDefaultTranslationsForLocale($locale)
        );
    }

    protected function getDefaultTranslationsForLocale(string $locale): array
    {
        $translations = [];

        foreach ($this->translationsLoader->namespaces() as $namespace => $hint) {
            $directory = Arr::first(File::directories($hint));
            foreach (File::files($directory) as $file) {
                $filename = $file->getFilename();
                $group = str_replace('.php', '', $filename);
                $translations = array_merge(
                    $translations,
                    Arr::dot($this->translationsLoader->load($locale, $group, $namespace), "$namespace::$group.")
                );
            }
        }

        $directory = App::resourcePath('lang'.DIRECTORY_SEPARATOR.$locale);
        if (File::exists($directory)) {
            foreach (File::files($directory) as $file) {
                $filename = $file->getFilename();
                $group = str_replace('.php', '', $filename);
                $translations = array_merge(
                    $translations,
                    Arr::dot($this->translationsLoader->load($locale, $group), "$group.")
                );
            }
        }

        $translations = array_merge(
            $translations,
            Arr::dot($this->translationsLoader->load($locale, '*', '*'))
        );

        return array_filter($translations, function ($value) {
            return is_string($value);
        });
    }

    /**
     * @param  string  $locale
     */
    public function removeLocale(string $locale)
    {
        $this->disk->delete($this->getPath($locale));
    }

    /**
     * @param  string  $locale
     * @param  string  $key
     * @param  string  $value
     * @param  string|null  $namespace
     */
    public function updateTranslation(string $locale, string $key, string $value, ?string $namespace = null)
    {
        $translations = $this->getTranslations($locale);

        $translations[$this->getKey($key, $namespace)] = $value;

        $this->save($locale, $translations);
    }

    /**
     * @param  string  $locale
     * @param  string  $key
     * @param  string|null  $namespace
     * @throws InvalidArgumentException if given locale does not exists
     */
    public function removeTranslation(string $locale, string $key, ?string $namespace = null)
    {
        $key = $this->getKey($key, $namespace);
        $translations = array_filter(
            $this->getTranslations($locale),
            function (string $k) use ($key, $namespace) {
                return $k !== $key;
            },
            ARRAY_FILTER_USE_KEY
        );

        $this->save($locale, $translations);
    }
}
