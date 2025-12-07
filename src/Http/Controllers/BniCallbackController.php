<?php

namespace ESolution\BNIPayment\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use ESolution\BNIPayment\Models\BniPaymentLog;

class BniCallbackController extends Controller
{
    public function va(Request $request)
    {
        BniPaymentLog::create([
            'channel' => 'va',
            'request_payload' => $request->all(),
            'response_payload' => ['received' => true],
            'status' => $request->input('status'),
            'ip' => $request->ip()
        ]);

        return response()->json(['status' => "000"]);
    }

    public function qris(Request $request)
    {
        BniPaymentLog::create([
            'channel' => 'qris',
            'request_payload' => $request->all(),
            'response_payload' => ['received' => true],
            'status' => $request->input('status'),
            'ip' => $request->ip()
        ]);

        return response()->json(['status' => "000"]);
    }
}
