<?php

namespace ESolution\BNIPayment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;
use ESolution\BNIPayment\Models\BniBilling;

class BniBillingExpired
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public BniBilling $billing) {}
}
