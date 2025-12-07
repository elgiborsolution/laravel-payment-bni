<?php

namespace ESolution\BNIPayment\Console;

use Illuminate\Console\Command;
use ESolution\BNIPayment\Services\Reconciler;

class BniReconcileCommand extends Command
{
    protected $signature = 'bni:reconcile {--limit=100}';
    protected $description = 'Reconcile BNI eCollection billings by inquiring their latest status.';

    public function handle(Reconciler $reconciler): int
    {
        $clientId = config('bni.client_id');
        $prefix = config('bni.prefix');
        $secret = config('bni.secret');

        $limit = (int) $this->option('limit');
        $res = $reconciler->reconcile($limit, $clientId, $prefix, $secret);
        $this->info('Processed: ' . $res['processed'] . ' | Paid: ' . $res['paid'] . ' | Errors: ' . $res['errors']);
        return self::SUCCESS;
    }
}
