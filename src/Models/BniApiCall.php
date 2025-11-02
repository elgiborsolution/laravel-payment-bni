<?php

namespace ESolution\BNIPayment\Models;

use Illuminate\Database\Eloquent\Model;

class BniApiCall extends Model
{
    protected $table = 'bni_api_calls';

    protected $fillable = [
        'channel','endpoint','method','http_status',
        'request_body','response_body','bni_status','bni_code',
        'ip','user_id','correlation_id'
    ];

    protected $casts = [
        'request_body' => 'array',
        'response_body' => 'array',
    ];
}
