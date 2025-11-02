<?php

namespace ESolution\BNIPayment\Console;

use Illuminate\Console\Command;

class BniConfigGetCommand extends Command
{
    protected $signature = 'bni:config:get {key} {--default=}';
    protected $description = 'Get BNI config key (DB overrides, fallback file config).';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $def = $this->option('default') ?? null;
        $val = app('bni.config')->get($key, $def);
        $this->line(json_encode([$key => $val], JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
