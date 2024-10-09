<?php

namespace Laranex\LaravelMoney\Commands;

use Illuminate\Console\Command;

class LaravelMoneyCommand extends Command
{
    public $signature = 'laravel-money';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
