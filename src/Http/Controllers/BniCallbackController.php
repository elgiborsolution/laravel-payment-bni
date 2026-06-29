<?php

namespace ESolution\BNIPayment\Http\Controllers;

use ESolution\BNIPayment\Http\Controllers\Concerns\LogsBniControllerActivity;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use ESolution\BNIPayment\Models\BniPaymentLog;
use ESolution\BNIPayment\Services\BniQrisAuth;
use ESolution\BNIPayment\Models\BniBilling;
use ESolution\BNIPayment\Events\BniBillingPaid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Event;

class BniCallbackController extends Controller
{
    use LogsBniControllerActivity;

    public function va(Request $request)
    {
        $startedAt = microtime(true);

        try {
            $this->bniLogRequest($request, 'va.callback.received', [
                'channel' => 'va',
                'client_id' => $request->input('client_id', ''),
            ]);

            $log = BniPaymentLog::create([
                'client_id' => $request->input('client_id',),
                'channel' => 'va',
                'request_payload' => $request->all(),
                'response_payload' => ['received' => true],
                'status' => $request->input('status'),
                'ip' => $request->ip()
            ]);

            $responsePayload = ['status' => '000'];
            $response = response()->json($responsePayload);

            $this->bniLogResponse($request, 'va.callback.success', [
                'channel' => 'va',
                'payment_log_id' => $log->id ?? null,
                'response' => $responsePayload,
            ], $startedAt);

            return $response;
        } catch (\Throwable $e) {
            $this->bniLogException($request, 'va.callback.exception', $e, [
                'channel' => 'va',
            ], $startedAt);

            throw $e;
        }
    }

    public function qris(Request $request, $tenantId = null)
    {
        $startedAt = microtime(true);

        try {
            $this->bniLogRequest($request, 'qris.callback.received', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
            ]);

            // ===== INIT TENANT (SAFE) =====
            $tenant = $this->initializeTenantIfNeeded($tenantId);
            $this->bniLogRequest($request, 'qris.tenant.initialized', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'tenant_initialized' => (bool) $tenant,
            ]);

            // ===== STORE RAW CALLBACK LOG =====
            $this->bniLogRequest($request, 'qris.raw_callback_logging', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
            ]);

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

            $this->bniLogRequest($request, 'qris.authorization.received', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'authorization_present' => ! empty($token),
            ]);

            if (! $token || !str_starts_with($token, 'Bearer ')) {
                $response = response()->json([
                    'responseCode' => '4003401',
                    'responseMessage' => 'Invalid Field Format',
                ], 400);

                $this->bniLogResponse($request, 'qris.validation.failed', [
                    'channel' => 'qris',
                    'tenant_id' => $tenantId,
                    'response' => [
                        'responseCode' => '4003401',
                        'responseMessage' => 'Invalid Field Format',
                    ],
                    'reason' => 'invalid_authorization_header',
                ], $startedAt);

                return $response;
            }

            $token = str_replace('Bearer ', '', $token);
            $this->bniLogRequest($request, 'qris.authorization.token_extracted', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
            ]);

            $tokenData = DB::table('bni_access_tokens')
                ->where('token', $token)
                ->where('expires_at', '>', now())
                ->first();

            $this->bniLogRequest($request, 'qris.token.lookup.completed', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'token_valid' => (bool) $tokenData,
            ]);

            if (! $tokenData) {
                $response = response()->json([
                    'responseCode' => '4013400',
                    'responseMessage' => 'Unauthorized. Verify Token Auth.',
                ], 401);

                $this->bniLogResponse($request, 'qris.authorization.failed', [
                    'channel' => 'qris',
                    'tenant_id' => $tenantId,
                    'response' => [
                        'responseCode' => '4013400',
                        'responseMessage' => 'Unauthorized. Verify Token Auth.',
                    ],
                    'reason' => 'token_invalid_or_expired',
                ], $startedAt);

                return $response;
            }

            // ====================== START CALLBACK CONTROLLER ===================
            $this->bniLogRequest($request, 'qris.authorization.passed', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
            ]);

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

            $this->bniLogRequest($request, 'qris.validation.completed', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'validation_failed' => $validator->fails(),
            ]);

            // ===== LOAD CALLBACK CONFIG =====
            $clientId  = $tokenData->client_id;
            $callbackConfigAll = config('bni.callback');

            $this->bniLogRequest($request, 'qris.callback.config_loaded', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'client_id' => $clientId,
            ]);

            if (empty($callbackConfigAll[$clientId])) {
                $response = response()->json([
                    'responseCode' => '4017300',
                    'responseMessage' => 'Unauthorized Client',
                ], 401);

                $this->bniLogResponse($request, 'qris.authorization.failed', [
                    'channel' => 'qris',
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'response' => [
                        'responseCode' => '4017300',
                        'responseMessage' => 'Unauthorized Client',
                    ],
                    'reason' => 'client_not_configured',
                ], $startedAt);

                return $response;
            }

            $config = $callbackConfigAll[$clientId];
            $this->bniLogRequest($request, 'qris.callback.config_assigned', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'client_id' => $clientId,
            ]);

            // ===== BUILD SIGNATURE =====
            $auth  = new BniQrisAuth($config);

            $timestamp = $request->header('X-TIMESTAMP');
            $signature = $request->header('X-SIGNATURE') ?? '';

            $this->bniLogRequest($request, 'qris.signature.headers_received', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'timestamp' => $timestamp,
                'signature_present' => ! empty($signature),
            ]);

            $absoluteUrl = $request->fullUrl();
            $url = $request->getPathInfo();

            $pos = strpos($url, '/snap/');
            $urlSnap = $pos !== false ? substr($url, $pos) : '';

            $this->bniLogRequest($request, 'qris.url.resolved', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'absolute_url' => $absoluteUrl,
                'path' => $url,
                'snap_path' => $urlSnap,
            ]);

            $body = $request->getContent();
            $bodyRaw = json_decode($body, true) ?? [];

            $this->bniLogRequest($request, 'qris.raw_body.captured', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'raw_body' => $this->bniMaskData($bodyRaw),
            ]);

            $expected = $auth->buildRequestSignature(
                'POST',
                $url,
                $bodyRaw,
                $timestamp,
                $token
            );

            $this->bniLogRequest($request, 'qris.signature.generated', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'signature_path' => 'full',
            ]);

            $expectedAlt = $auth->buildRequestSignature(
                'POST',
                $urlSnap,
                $bodyRaw,
                $timestamp,
                $token
            );

            $this->bniLogRequest($request, 'qris.signature.generated', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'signature_path' => 'snap',
            ]);

            // ===== SIGNATURE COMPARISON =====
            $valid = hash_equals($signature, $expected) || hash_equals($signature, $expectedAlt);

            $this->bniLogRequest($request, 'qris.signature.compared', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'is_valid' => $valid,
            ]);

            // ===== UPDATE BILLING IF EXISTS =====
            $billing = BniBilling::where('qris_reference_no', $request->originalReferenceNo)->first();

            $this->bniLogRequest($request, 'qris.billing.lookup.completed', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'reference_no' => $request->originalReferenceNo,
                'billing_found' => (bool) $billing,
            ]);

            if (!empty($billing)) {
                $billing->update([
                    'payment_amount' => $request->amount['value']??0,
                    'qris_status' => $request->latestTransactionStatus,
                    'paid_at' => isset($request->additionalInfo['paidTime']) ? date('Y-m-d H:i:s', strtotime($request->additionalInfo['paidTime'])) : null
                ]);

                $this->bniLogRequest($request, 'qris.billing.updated', [
                    'channel' => 'qris',
                    'tenant_id' => $tenantId,
                    'payment_amount' => $request->amount['value']??0,
                    'status' => $request->latestTransactionStatus,
                    'paid_at' => isset($request->additionalInfo['paidTime']) ? date('Y-m-d H:i:s', strtotime($request->additionalInfo['paidTime'])) : null
                ]);

                Event::dispatch(new BniBillingPaid($billing, $tenantId));
                $this->bniLogRequest($request, 'qris.billing.paid.event_dispatched', [
                    'channel' => 'qris',
                    'tenant_id' => $tenantId,
                ]);
            }

            $responsePayload = [
                'responseCode' => '2005200',
                'responseMessage' => ($valid ? 'Request has been processed successfully' : 'Unauthorized. [Invalid X-SIGNATURE]')
            ];

            $response = response()->json($responsePayload, 200);

            $this->bniLogResponse($request, 'qris.callback.completed', [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
                'response' => $responsePayload,
            ], $startedAt);

            return $response;
        } catch (\Throwable $e) {
            $this->bniLogException($request, 'qris.callback.exception', $e, [
                'channel' => 'qris',
                'tenant_id' => $tenantId,
            ], $startedAt);

            throw $e;
        }
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
