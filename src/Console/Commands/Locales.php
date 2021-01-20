<?php

namespace SoluzioneSoftware\LaravelTranslations\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use SoluzioneSoftware\LaravelTranslations\Facades\Translations;
use Symfony\Component\Console\Exception\RuntimeException;

class Locales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:locales 
                            {action : The action to perform(list, add, remove)}
                            {locale? : Required for: add, remove}
                            {--initialize : Whether the locale should be initialized with default values. Only for add action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage locales';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        if ($action === 'list') {
            $this->listAction();
        } elseif ($action === 'add') {
            $this->addAction();
        } elseif ($action === 'remove') {
            $this->removeAction();
        } else {
            throw new RuntimeException("$action is not a valid action");
        }
    }

    private function listAction()
    {
        $locales = Translations::getLocales();
        $this->output->writeln(implode(' ', $locales));
    }

    private function addAction()
    {
        $locale = $this->getLocaleArgumentOrFail();
        try {
            Translations::addLocale($locale, $this->option('initialize'));
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException($exception->getMessage(), $exception->getCode());
        }
        $this->info("Locale $locale added.");
    }

    private function getLocaleArgumentOrFail()
    {
        $locale = $this->argument('locale');
        if (empty($locale)) {
            throw new RuntimeException("<locale> is required");
        }
        return $locale;
    }

    private function removeAction()
    {
        $locale = $this->getLocaleArgumentOrFail();
        $confirmed = $this->confirm("All translations will be removed for '$locale' locale. Continue?");
        if ($confirmed) {
            Translations::removeLocale($locale);
            $this->info("Locale $locale removed.");
        }
    }
}
