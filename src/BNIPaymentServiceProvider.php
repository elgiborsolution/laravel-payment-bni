<?php

namespace ESolution\BNIPayment;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class BNIPaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bni.php', 'bni');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/bni.php' => config_path('bni.php')], 'bni-config');

        if (! class_exists('CreateBniTables')) {
            $this->publishes([
                __DIR__ . '/../database/migrations/2025_11_02_000000_create_bni_tables.php' => database_path('migrations/2025_11_02_000000_create_bni_tables.php'),
                __DIR__ . '/../database/migrations/2025_11_02_000200_create_bni_billings_table.php' => database_path('migrations/2025_11_02_000200_create_bni_billings_table.php'),
            ], 'bni-migrations');
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \ESolution\BNIPayment\Console\BniReconcileCommand::class
            ]);
        }

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
            if (config('bni.schedule.enabled', true)) {
                $cron = config('bni.schedule.cron', '*/5 * * * *');
                $schedule->command('bni:reconcile')->cron($cron)->onOneServer();
            }
        });
    }
}
