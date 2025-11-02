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
        $limit = (int) $this->option('limit');
        $res = $reconciler->reconcile($limit);
        $this->info('Processed: '.$res['processed'].' | Paid: '.$res['paid'].' | Errors: '.$res['errors']);
        return self::SUCCESS;
    }
}
