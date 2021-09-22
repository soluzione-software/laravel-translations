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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class Manager
{
    const SOURCE_APP = 'app';
    const SOURCE_VENDOR = 'vendor';
    /**
     * @var Filesystem
     */
    protected $disk;
    /**
     * @var string
     */
    protected $path;
    /**
     * @var Loader|FileLoader
     */
    protected $translationsLoader;
    /**
     * @var string
     */
    protected $langPath;

    /**
     * Manager constructor.
     */
    public function __construct(string $langPath)
    {
        $this->disk = Storage::disk(Config::get('translations.disk'));
        $this->path = Config::get('translations.path');
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->translationsLoader = Container::getInstance()->make('translation.loader');
        $this->langPath = $langPath;
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
                return str_replace('.json', '', Arr::last(explode('/', $file)));
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
        return "$this->path/$locale.json";
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
        return $this->mergeTranslations(
            collect($this->getDefaultTranslationsForLocale(Config::get('app.fallback_locale'))),
            collect($this->getDefaultTranslationsForLocale($locale))
        );
    }

    protected function mergeTranslations(Collection ...$array): Collection
    {
        $merged = [];

        foreach ($array as $translations) {
            $translations
                ->each(function (array $translation) use (&$merged) {
                    $key = $this->getFullKey($translation['key'], $translation['namespace']);
                    if (array_key_exists($key, $merged)) {
                        $merged[$key]['value'] = $translation['value'];
                    } else {
                        $merged[$key] = $translation;
                    }
                });
        }

        return collect(array_values($merged));
    }

    protected function getFullKey(string $key, ?string $namespace = null): string
    {
        return (!empty($namespace) ? $namespace.'::' : '').$key;
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
                    $this->load($locale, $group, $namespace)
                );
            }
        }

        $directory = "$this->langPath/$locale";
        if (File::exists($directory)) {
            foreach (File::files($directory) as $file) {
                $filename = $file->getFilename();
                $group = str_replace('.php', '', $filename);
                $translations = array_merge(
                    $translations,
                    $this->load($locale, $group)
                );
            }
        }

        $translations = array_merge(
            $translations,
            $this->load($locale, '*', '*')
        );

        return array_filter(
            $translations,
            function (array $translation) {
                return is_string($translation['value']);
            }
        );
    }

    protected function load(string $locale, string $group, ?string $namespace = null): array
    {
        if ($group === '*' && $namespace === '*') {
            return $this->loadJsonPaths($locale);
        }

        if (is_null($namespace) || $namespace === '*') {
            $translations = $this->loadPath($this->langPath, $locale, $group);
            return $this->mapTranslations(Arr::dot($translations), self::SOURCE_APP, $group);
        }

        return $this->mapTranslations(
            Arr::dot($this->loadNamespaced($locale, $group, $namespace)),
            self::SOURCE_VENDOR,
            $group,
            $namespace
        );
    }

    /**
     * Load a locale from the given JSON file path.
     *
     * @param  string  $locale
     * @return array
     *
     * @throws RuntimeException
     */
    protected function loadJsonPaths(string $locale)
    {
        $output = $this->mapTranslations($this->loadJsonPath($this->langPath, $locale), self::SOURCE_APP, '*');

        foreach ($this->translationsLoader->jsonPaths() as $jsonPath) {
            $output = array_merge(
                $output,
                $this->mapTranslations($this->loadJsonPath($jsonPath, $locale), self::SOURCE_VENDOR, '*')
            );
        }
        return $output;
    }

    /**
     * @param  array  $translations
     * @param  string  $source
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array
     */
    protected function mapTranslations(
        array $translations,
        string $source,
        string $group,
        ?string $namespace = null
    ) {
        $result = [];

        foreach ($translations as $key => $value) {
            $result[] = [
                'source' => $source,
                'namespace' => $namespace,
                'key' => ($group !== '*' ? $group.'.' : '').$key,
                'value' => $value,
            ];
        }

        return $result;
    }

    /**
     * Load a locale from the given JSON file path.
     *
     * @param  string  $path
     * @param  string  $locale
     * @return array
     *
     */
    protected function loadJsonPath(string $path, string $locale)
    {
        if (File::exists($full = "$path/$locale.json")) {
            $decoded = json_decode(File::get($full), true);

            if (is_null($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException("Translation file [$full] contains an invalid JSON structure.");
            }

            return $decoded;
        }
        return [];
    }

    /**
     * Load a locale from a given path.
     *
     * @param  string  $path
     * @param  string  $locale
     * @param  string  $group
     * @return array
     */
    protected function loadPath(string $path, string $locale, string $group)
    {
        if (File::exists($full = "$path/$locale/$group.php")) {
            return File::getRequire($full);
        }

        return [];
    }

    /**
     * Load a namespaced translation group.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string  $namespace
     * @return array
     */
    protected function loadNamespaced(string $locale, string $group, string $namespace)
    {
        if (isset($this->translationsLoader->namespaces()[$namespace])) {
            $lines = $this->loadPath($this->translationsLoader->namespaces()[$namespace], $locale, $group);

            return $this->loadNamespaceOverrides($lines, $locale, $group, $namespace);
        }

        return [];
    }

    /**
     * Load a local namespaced translation group for overrides.
     *
     * @param  array  $lines
     * @param  string  $locale
     * @param  string  $group
     * @param  string  $namespace
     * @return array
     */
    protected function loadNamespaceOverrides(array $lines, string $locale, string $group, string $namespace)
    {
        $file = "$this->path/vendor/$namespace/$locale/$group.php";

        if (File::exists($file)) {
            return array_replace_recursive($lines, File::getRequire($file));
        }

        return $lines;
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
                    'source' => $row[0],
                    'namespace' => $row[1],
                    'key' => $row[2],
                    'value' => $row[3],
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
        $translation = [
            'namespace' => $namespace,
            'key' => $key,
            'value' => $value,
        ];

        $this->save(
            $locale,
            $this->mergeTranslations(
                $this->getTranslations($locale),
                collect([
                    $translation,
                ])
            )
        );
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

        $keys = ['source', 'namespace', 'key', 'value'];
        fputcsv($handle, $keys);
        foreach ($this->getTranslations($locale) as $translation) {
            fputcsv($handle, Arr::only($translation, $keys));
        }

        fclose($handle);
    }

    public function sync(string $locale)
    {
        $this->save(
            $locale,
            $this->mergeTranslations(
                $this->getDefaultTranslations($locale),
                $this->getTranslations($locale)
            )
        );
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
