<?php

/**
 * @noinspection PhpDocMissingThrowsInspection
 * @noinspection PhpRedundantCatchClauseInspection
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection PhpUnused
 */

namespace SoluzioneSoftware\LaravelTranslations;

use ErrorException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
        $translation = $this
            ->getTranslations($locale)
            ->first(
                function (array $item) use ($namespace, $key) {
                    return $item['key'] === $key && $item['namespace'] === $namespace;
                }
            );
        return $translation ? $translation['value'] : null;
    }

    protected function hasLocale(string $locale): bool
    {
        return $this->getLocales()->contains($locale);
    }

    public function getLocales(): Collection
    {
        return collect($this->disk->allFiles($this->path))
            ->map(function (string $file) {
                return str_replace('.json', '', Arr::last(explode(DIRECTORY_SEPARATOR, $file)));
            });
    }

    /**
     * @param  string  $locale
     * @return Collection
     * @throws InvalidArgumentException if given locale does not exists
     */
    public function getTranslations(string $locale): Collection
    {
        if (!$this->hasLocale($locale)) {
            throw new InvalidArgumentException("Locale $locale does not exists");
        }

        return collect(array_values(json_decode($this->disk->get($this->getPath($locale)), true)));
    }

    protected function getPath(string $locale): string
    {
        return $this->path.DIRECTORY_SEPARATOR."$locale.json";
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

        $this->save($locale, $initialize ? $this->getDefaultTranslations($locale) : collect());
    }

    protected function save(string $locale, Collection $translations)
    {
        $this->disk->put($this->getPath($locale), json_encode($translations->toArray()));
    }

    public function getDefaultTranslations(string $locale): Collection
    {
        $translations = array_merge(
            $this->getDefaultTranslationsForLocale(Config::get('app.fallback_locale')),
            $this->getDefaultTranslationsForLocale($locale)
        );

        array_walk(
            $translations,
            function (&$value, string $key) {
                $namespaceKey = explode('::', $key, 2);

                $value = [
                    'namespace' => count($namespaceKey) === 1 ? null : $namespaceKey[0],
                    'key' => Arr::last($namespaceKey),
                    'value' => $value,
                ];
            }
        );

        return collect(array_values($translations));
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
     * @param  string  $file
     * @throws InvalidArgumentException if given file is not readable
     */
    public function import(string $locale, string $file)
    {
        try {
            if (($handle = fopen($file, 'r')) === false) {
                throw new InvalidArgumentException("$file is not readable");
            }
        } catch (ErrorException $e) {
            throw new InvalidArgumentException("$file is not readable");
        }

        $translations = collect();
        $count = 1;
        while (($row = fgetcsv($handle)) !== false) {
            if ($count > 1) {
                $translations->push([
                    'namespace' => $row[0],
                    'key' => $row[1],
                    'value' => $row[2],
                ]);
            }

            $count++;
        }

        fclose($handle);

        $this->save($locale, $translations);
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

        $translations = $translations->map(function (array $translation) use ($value, $key, $namespace) {
            if ($translation['key'] === $key && $translation['namespace'] === $namespace) {
                $translation['value'] = $value;
            }
            return $translation;
        });

        $this->save($locale, $translations);
    }

    /**
     * @param  string  $locale
     * @param  string  $file
     * @throws InvalidArgumentException if given file is not writable
     */
    public function export(string $locale, string $file)
    {
        if (($handle = fopen($file, 'w')) === false) {
            throw new InvalidArgumentException("$file is not writable");
        }

        $keys = ['namespace', 'key', 'value'];
        fputcsv($handle, $keys);
        foreach ($this->getTranslations($locale) as $translation) {
            fputcsv($handle, Arr::only($translation, $keys));
        }

        fclose($handle);
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
     * @param  string|null  $namespace
     * @throws InvalidArgumentException if given locale does not exists
     */
    public function removeTranslation(string $locale, string $key, ?string $namespace = null)
    {
        $translations = $this->getTranslations($locale)
            ->filter(function (array $item) use ($key, $namespace) {
                return $item['key'] !== $key || $item['namespace'] !== $namespace;
            });

        $this->save($locale, $translations);
    }
}
