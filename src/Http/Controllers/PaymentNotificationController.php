<?php

namespace ESolution\BNIPayment\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ESolution\BNIPayment\Models\BniApiCall;
use ESolution\BNIPayment\Events\BniPaymentReceived;

class PaymentNotificationController extends Controller
{
    public function receive(Request $request)
    {
        $data = $request->all();

        $log = BniApiCall::create([
            'channel' => 'va',
            'endpoint' => '/webhook/payment',
            'method' => 'POST',
            'http_status' => 200,
            'request_body' => $data,
            'response_body' => ['status' => '000'],
            'bni_status' => '000',
            'bni_code' => '000',
            'ip' => $request->ip(),
            'user_id' => auth()->id() ?? null,
            'correlation_id' => (string) Str::uuid(),
        ]);

        event(new BniPaymentReceived($data, $log->id));
        return response()->json(['status' => '000']);
    }
}
