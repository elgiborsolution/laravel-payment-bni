<?php

namespace ESolution\BNIPayment\Models;

use Illuminate\Database\Eloquent\Model;

class BniBilling extends Model
{
    protected $table = 'bni_billings';
    protected $fillable = [
        'trx_id',
        'virtual_account',
        'trx_amount',
        'customer_name',
        'customer_email',
        'customer_phone',
        'billing_type',
        'description',
        'va_status',
        'payment_amount',
        'payment_ntb',
        'paid_at',
        'expired_at',
        'last_inquiry_at',
        'qris_bill_number',
        'qris_status',
        'qris_content',
        'qris_reference_no',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'last_inquiry_at' => 'datetime',
    ];
}
