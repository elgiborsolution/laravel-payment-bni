<?php

namespace ESolution\BNIPayment\Models;

use Illuminate\Database\Eloquent\Model;

class BniConfig extends Model
{
    protected $table = 'bni_configs';
    protected $fillable = ['key','value','description'];
}
