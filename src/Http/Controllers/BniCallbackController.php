<?php

namespace ESolution\BNIPayment\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use ESolution\BNIPayment\Models\BniApiCall;
use Illuminate\Support\Str;

class BniCallbackController extends Controller
{
    public function va(Request $request)
    {
        BniApiCall::create([
            'channel' => 'va',
            'endpoint' => '/callback/va',
            'method' => 'POST',
            'http_status' => 200,
            'request_body' => $request->all(),
            'response_body' => ['received' => true],
            'bni_status' => $request->input('status'),
            'bni_code' => $request->input('status'),
            'ip' => $request->ip(),
            'user_id' => auth()->id() ?? null,
            'correlation_id' => (string) Str::uuid(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function qris(Request $request)
    {
        BniApiCall::create([
            'channel' => 'qris',
            'endpoint' => '/callback/qris',
            'method' => 'POST',
            'http_status' => 200,
            'request_body' => $request->all(),
            'response_body' => ['received' => true],
            'bni_status' => $request->input('status'),
            'bni_code' => $request->input('status'),
            'ip' => $request->ip(),
            'user_id' => auth()->id() ?? null,
            'correlation_id' => (string) Str::uuid(),
        ]);

        return response()->json(['ok' => true]);
    }
}
