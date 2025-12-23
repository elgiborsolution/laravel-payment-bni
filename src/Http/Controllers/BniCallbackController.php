<?php

namespace ESolution\BNIPayment\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use ESolution\BNIPayment\Models\BniPaymentLog;
use ESolution\BNIPayment\Services\BniQrisAuth;
use ESolution\BNIPayment\Models\BniBilling;
use ESolution\BNIPayment\Events\BniBillingPaid;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Event;

class BniCallbackController extends Controller
{
    public function va(Request $request)
    {
        BniPaymentLog::create([
            'client_id' => $request->input('client_id',),
            'channel' => 'va',
            'request_payload' => $request->all(),
            'response_payload' => ['received' => true],
            'status' => $request->input('status'),
            'ip' => $request->ip()
        ]);

        return response()->json(['status' => "000"]);
    }

    public function qris(Request $request, $tenantId = null)
    {
        $enableLog = config('bni.callback_debug')??false; // ← set true jika ingin aktifkan log

        $log = function (string $message, array $context = []) use ($enableLog) {
            if ($enableLog) {
                Log::info($message, $context);
            }
        };

        $log('[BNI QRIS] Callback started');

        $log('[BNI QRIS] Callback Header', $request->headers->all());

        // ===== INIT TENANT (SAFE) =====
        $tenant = $this->initializeTenantIfNeeded($tenantId);
        $log('[BNI QRIS] Tenant initialized', [
            'tenant_id' => $tenantId,
        ]);

        // ===== STORE RAW CALLBACK LOG =====
        $log('[BNI QRIS] Storing raw callback log');

        BniPaymentLog::create([
            'client_id' => $request->input('client_id', ''),
            'channel' => 'qris',
            'request_payload' => $request->all(),
            'response_payload' => ['received' => true],
            'status' => $request->input('latestTransactionStatus') ?? null,
            'ip' => $request->ip()
        ]);

        // ====================== VALIDATE AUTH ===================
        $token = $request->header('Authorization');

        $log('[BNI QRIS] Authorization header received', [
            'authorization_present' => !empty($token),
        ]);

        if (!$token || !str_starts_with($token, 'Bearer ')) {
            $log('[BNI QRIS] Invalid Authorization header format');

            return response()->json([
                'responseCode' => '4003401',
                'responseMessage' => 'Invalid Field Format',
            ], 400);
        }

        $token = str_replace('Bearer ', '', $token);
        $log('[BNI QRIS] Bearer token extracted');

        $tokenData = DB::table('bni_access_tokens')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        $log('[BNI QRIS] Token lookup executed', [
            'token_valid' => (bool) $tokenData,
        ]);

        if (!$tokenData) {
            $log('[BNI QRIS] Token invalid or expired');

            return response()->json([
                'responseCode' => '4013400',
                'responseMessage' => 'Unauthorized. Verify Token Auth.',
            ], 401);
        }

        // ====================== START CALLBACK CONTROLLER ===================
        $log('[BNI QRIS] Token authentication passed');

        $validator = Validator::make($request->all(), [
            'originalReferenceNo'         => ['required', 'string'],
            'originalPartnerReferenceNo'  => ['nullable', 'string'],
            'latestTransactionStatus'     => ['nullable', 'string', 'size:2', 'in:00,01,02,03,04,05,06,07'],
            'transactionStatusDesc'       => ['nullable', 'string'],
            'amount'                      => ['required', 'array'],
            'amount.value'                => ['required', 'numeric', 'min:0'],
            'amount.currency'             => ['required', 'string', 'size:3'],
            'additionalInfo'              => ['nullable', 'array'],
        ]);

        $log('[BNI QRIS] Payload validation executed', [
            'validation_failed' => $validator->fails(),
        ]);

        // ===== LOAD CALLBACK CONFIG =====
        $clientId  = $tokenData->client_id;
        $callbackConfigAll = config('bni.callback');

        $log('[BNI QRIS] Callback config loaded');

        if (empty($callbackConfigAll[$clientId])) {
            $log('[BNI QRIS] Client not found in callback config', [
                'client_id' => $clientId,
            ]);

            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized Client',
            ], 401);
        }

        $config = $callbackConfigAll[$clientId];
        $log('[BNI QRIS] Client callback config assigned');

        // ===== BUILD SIGNATURE =====
        $auth  = new BniQrisAuth($config);

        $timestamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE') ?? '';

        $log('[BNI QRIS] Signature headers received', [
            'timestamp' => $timestamp,
            'signature_present' => !empty($signature),
        ]);

        $absoluteUrl = $request->fullUrl();
        $url = $request->getPathInfo();

        $pos = strpos($url, '/snap/');
        $urlSnap = $pos !== false ? substr($url, $pos) : '';

        $log('[BNI QRIS] URL resolved', [
            'absolute_url' => $absoluteUrl,
            'path' => $url,
            'snap_path' => $urlSnap,
        ]);

        $body = $request->getContent();
        $bodyRaw = json_decode($body, true) ?? [];

        $log('[BNI QRIS] Raw body captured', [
            'raw_body' => $bodyRaw,
        ]);

        $expected = $auth->buildRequestSignature(
            'POST',
            $url,
            $bodyRaw,
            $timestamp,
            $token
        );

        $log('[BNI QRIS] Signature generated using full path');

        $expectedAlt = $auth->buildRequestSignature(
            'POST',
            $urlSnap,
            $bodyRaw,
            $timestamp,
            $token
        );

        $log('[BNI QRIS] Signature generated using snap path');

        // ===== SIGNATURE COMPARISON =====
        $valid = hash_equals($signature, $expected) || hash_equals($signature, $expectedAlt);

        $log('[BNI QRIS] Signature comparison result', [
            'is_valid' => $valid,
        ]);

        // ===== UPDATE BILLING IF EXISTS =====
        $billing = BniBilling::where('qris_reference_no', $request->originalReferenceNo)->first();

        $log('[BNI QRIS] Billing lookup executed', [
            'reference_no' => $request->originalReferenceNo,
            'billing_found' => (bool) $billing,
        ]);

        if (!empty($billing)) {
            $billing->update([
                'payment_amount' => $request->amount['value']??0,
                'qris_status' => $request->latestTransactionStatus,
                'paid_at' => isset($request->additionalInfo['paidTime']) ? date('Y-m-d H:i:s', strtotime($request->additionalInfo['paidTime'])) : null
            ]);

            $log('[BNI QRIS] Billing status updated', [
                'payment_amount' => $request->amount['value']??0,
                'status' => $request->latestTransactionStatus,
                'paid_at' => isset($request->additionalInfo['paidTime']) ? date('Y-m-d H:i:s', strtotime($request->additionalInfo['paidTime'])) : null
            ]);

            Event::dispatch(new BniBillingPaid($billing, $tenantId));
            $log('[BNI QRIS] BniBillingPaid event dispatched');
        }

        $log('[BNI QRIS] Callback completed');

        return response()->json([
            'responseCode' => '2005200',
            'responseMessage' => ($valid ? 'Request has been processed successfully' : 'Unauthorized. [Invalid X-SIGNATURE]')
        ], 200);
    }


    protected function initializeTenantIfNeeded($tenantId = null): ?object
    {
        if (!$tenantId) {
            return null;
        }

        // tenancy() helper exists?
        if (!function_exists('tenancy')) {
            return null;
        }

        try {
            $tenantModel = config('tenancy.tenant_model');

            if (!$tenantModel || !class_exists($tenantModel)) {
                return null;
            }

            $tenant = $tenantModel::find($tenantId);

            if (!$tenant) {
                return null;
            }

            tenancy()->initialize($tenant);

            return $tenant;
        } catch (\Throwable $e) {
            // jangan pernah throw error di package callback
            report($e);
            return null;
        }
    }

}
