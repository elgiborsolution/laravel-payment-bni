<?php

namespace ESolution\BNIPayment\Http\Controllers;

use ESolution\BNIPayment\Http\Controllers\Concerns\LogsBniControllerActivity;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use ESolution\BNIPayment\Models\BniPaymentLog;
use ESolution\BNIPayment\Events\BniPaymentReceived;

class PaymentNotificationController extends Controller
{
    use LogsBniControllerActivity;

    public function receive(Request $request)
    {
        $startedAt = microtime(true);

        try {
            $this->bniLogRequest($request, 'payment-notification.request.received', [
                'channel' => 'va',
            ]);

            $data = $request->all();

            $log = BniPaymentLog::create([
                'client_id' => $request->input('client_id',),
                'channel' => 'va',
                'request_payload' => $data,
                'response_payload' => ['status' => '000'],
                'status' => '000',
                'ip' => $request->ip()
            ]);

            event(new BniPaymentReceived($data, $log->id));

            $responsePayload = ['status' => '000'];
            $response = response()->json($responsePayload);

            $this->bniLogResponse($request, 'payment-notification.success', [
                'channel' => 'va',
                'payment_log_id' => $log->id ?? null,
                'response' => $responsePayload,
            ], $startedAt);

            return $response;
        } catch (\Throwable $e) {
            $this->bniLogException($request, 'payment-notification.exception', $e, [
                'channel' => 'va',
            ], $startedAt);

            throw $e;
        }
    }
}
