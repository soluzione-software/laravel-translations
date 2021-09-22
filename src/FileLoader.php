<?php

namespace SoluzioneSoftware\LaravelTranslations;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Filesystem\Filesystem;

class FileLoader extends \Illuminate\Translation\FileLoader
{
    /**
     * @var Loader
     */
    private $loader;

    public function __construct(Loader $loader, Filesystem $files, string $path)
    {
        parent::__construct($files, $path);

        $this->loader = $loader;
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return \Illuminate\Translation\FileLoader::{$method}(...$parameters);
    }

    /**
     * @return array
     */
    public function jsonPaths()
    {
        return $this->jsonPaths;
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->loader->{$method}(...$parameters);
    }
}
