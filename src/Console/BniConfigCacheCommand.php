<?php

namespace ESolution\BNIPayment\Console;

use Illuminate\Console\Command;

class BniConfigCacheCommand extends Command
{
    protected $signature = 'bni:config:cache';
    protected $description = 'Warm BNI config cache from database.';

    public function handle(): int
    {
        app('bni.config')->clearCache();
        app('bni.config')->all();
        $this->info('BNI config cache warmed.');
        return self::SUCCESS;
    }
}
