<?php

namespace ESolution\BNIPayment\Console;

use Illuminate\Console\Command;

class BniConfigSetCommand extends Command
{
    protected $signature = 'bni:config:set {key} {value} {--description=}';
    protected $description = 'Set BNI config key in database (overrides file config).';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $value = (string) $this->argument('value');
        $desc = $this->option('description');

        app('bni.config')->set($key, $value, $desc);
        $this->info("Config [$key] saved.");
        return self::SUCCESS;
    }
}
