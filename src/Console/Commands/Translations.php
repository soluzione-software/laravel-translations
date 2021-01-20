<?php

namespace SoluzioneSoftware\LaravelTranslations\Console\Commands;

use Illuminate\Console\Command;

class Translations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:translations
                            {action : The action to perform(list, update, remove)}
                            {locale : Locale}
                            {key? : Translation key(with namespace). Only for [update, remove] actions}
                            {value? : Translation value. Only for update action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage app and package translations';

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
     *
     */
    public function handle()
    {
        // todo:
    }
}
