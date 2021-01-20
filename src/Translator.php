<?php

namespace SoluzioneSoftware\LaravelTranslations;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Support\Arr;
use Illuminate\Translation\Translator as BaseTranslator;
use SoluzioneSoftware\LaravelTranslations\Facades\Translations;

class Translator extends BaseTranslator implements TranslatorContract
{
    /**
     * @var TranslatorContract
     */
    private $translator;

    public function __construct(TranslatorContract $translator, Loader $loader)
    {
        $this->translator = $translator;
        parent::__construct($loader, $translator->getLocale());
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
        return BaseTranslator::{$method}(...$parameters);
    }

    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $namespaceKey = explode('::', $key, 2);
        $namespace = count($namespaceKey) === 1 ? null : $namespaceKey[0];

        $translation = Translations::getTranslation(
            $locale ?: $this->getLocale(),
            Arr::last($namespaceKey),
            $namespace
        );

        return $translation
            ? $this->makeReplacements($translation, $replace)
            : $this->translator->get($key, $replace = [], $locale);
    }

    public function getLocale()
    {
        return $this->translator->getLocale();
    }

    public function choice($key, $number, array $replace = [], $locale = null)
    {
        return $this->translator->choice($key, $number, $replace, $locale);
    }

    public function setLocale($locale)
    {
        $this->translator->setLocale($locale);
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
        return $this->translator->{$method}(...$parameters);
    }
}
