<?php

namespace ESolution\BNIPayment\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ESolution\BNIPayment\Models\BniPaymentLog;
use ESolution\BNIPayment\Events\BniPaymentReceived;

class PaymentNotificationController extends Controller
{
    public function receive(Request $request)
    {
        $data = $request->all();

        $log = BniPaymentLog::create([
            'channel' => 'va',
            'request_payload' => $data,
            'response_payload' => ['status' => '000'],
            'status' => '000',
            'ip' => $request->ip()
        ]);

        event(new BniPaymentReceived($data, $log->id));
        return response()->json(['status' => '000']);
    }
}
