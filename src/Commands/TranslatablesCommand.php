<?php

namespace Organi\Translatables\Commands;

use Illuminate\Console\Command;

class TranslatablesCommand extends Command
{
    public $signature = 'translatables';

    public $description = 'My command';

    public function handle()
    {
        $this->comment('All done');
    }
}
