<?php

namespace ESolution\BNIPayment\Models;

use Illuminate\Database\Eloquent\Model;

class BniPaymentLog extends Model
{
    protected $table = 'bni_payment_logs';

    protected $fillable = [
        'channel',
        'client_id',
        'tenant_id',
        'reff_id',
        'customer_no',
        'customer_name',
        'invoice_no',
        'qris_content',
        'va_number',
        'amount',
        'external_id',
        'expired_at',
        'paid_at',
        'request_payload',
        'response_payload',
        'callback_payload',
        'status',
        'ip'
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'callback_payload' => 'array',
    ];
}
