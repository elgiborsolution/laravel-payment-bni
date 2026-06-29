<?php

namespace ESolution\BNIPayment\Http\Controllers;

use ESolution\BNIPayment\Http\Controllers\Concerns\LogsBniControllerActivity;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;


class AuthTokenB2BController extends Controller
{
    use LogsBniControllerActivity;
    
    public function handle(Request $request, $tenantId = null)
    {
        $startedAt = microtime(true);

        try {
            $clientId  = $request->header('X-CLIENT-KEY');
            $timestamp = $request->header('X-TIMESTAMP');
            $signature = $request->header('X-SIGNATURE');

            $this->bniLogRequest($request, 'auth-token.request.received', [
                'tenant_id' => $tenantId,
                'client_id' => $clientId,
            ]);

            // Cek header wajib
            if (! $clientId || ! $timestamp || ! $signature) {
                $response = response()->json([
                    'responseCode' => '4007300',
                    'responseMessage' => 'Bad Request',
                ], 400);

                $this->bniLogResponse($request, 'auth-token.validation.failed', [
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'response' => [
                        'responseCode' => '4007300',
                        'responseMessage' => 'Bad Request',
                    ],
                    'reason' => 'missing_required_headers',
                ], $startedAt);

                return $response;
            }

            // Regex untuk ISO 8601
            $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/';
            if (! preg_match($pattern, $timestamp)) {
                $response = response()->json([
                    'responseCode' => '4007301',
                    'responseMessage' => 'Invalid Field Format',
                ], 400);

                $this->bniLogResponse($request, 'auth-token.validation.failed', [
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'response' => [
                        'responseCode' => '4007301',
                        'responseMessage' => 'Invalid Field Format',
                    ],
                    'reason' => 'invalid_timestamp_format',
                ], $startedAt);

                return $response;
            }

            // Ambil client
            $callbackConfigAll = config('bni.callback');

            if (empty($callbackConfigAll[$clientId])) {
                $response = response()->json([
                    'responseCode' => '4017300',
                    'responseMessage' => 'Unauthorized Client',
                ], 401);

                $this->bniLogResponse($request, 'auth-token.authorization.failed', [
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

            // String to sign
            $stringToSign = $clientId . '|' . $timestamp;

            // Decode signature Base64
            $decodedSignature = base64_decode($signature);

            $config = $callbackConfigAll[$clientId];

            // ============================
            // FUNCTION to load public key
            // ============================
            $loadPublicKey = function ($publicKeyString, $publicKeyPath) {
                // 1. Cek string public key langsung
                if (! empty($publicKeyString)) {
                    $key = openssl_pkey_get_public($publicKeyString);
                    if ($key !== false) {
                        return $key;
                    }
                }

                // 2. Fallback: cek file path
                if (! empty($publicKeyPath)) {
                    $fullPath = base_path($publicKeyPath);

                    if (file_exists($fullPath) && is_readable($fullPath)) {
                        $content = file_get_contents($fullPath);
                        $key = openssl_pkey_get_public($content);

                        if ($key !== false) {
                            return $key;
                        }
                    }
                }

                return null;
            };

            // Load public key
            $publicKeyQris = $loadPublicKey(
                null,
                $config['public_key_path'] ?? null
            );

            // Verifikasi
            $verifyQris = 0;
            if ($publicKeyQris) {
                $verifyQris = openssl_verify(
                    $stringToSign,
                    $decodedSignature,
                    $publicKeyQris,
                    OPENSSL_ALGO_SHA256
                );
            }

            // Gagal verification
            if ($verifyQris !== 1) {
                $response = response()->json([
                    'responseCode' => '4017300',
                    'responseMessage' => 'Unauthorized. [Invalid X-SIGNATURE]',
                ], 401);

                $this->bniLogResponse($request, 'auth-token.signature.failed', [
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'response' => [
                        'responseCode' => '4017300',
                        'responseMessage' => 'Unauthorized. [Invalid X-SIGNATURE]',
                    ],
                    'reason' => 'signature_verification_failed',
                ], $startedAt);

                return $response;
            }

            // Generate token
            $token = Str::random(64);

            $tenant = $this->initializeTenantIfNeeded($tenantId);

            $timeoutToken = config('bni.oauth_timeout_minutes', 15); // default 15 menit

            $expiresAt = now()->addMinutes($timeoutToken);

            DB::table('bni_access_tokens')->insert([
                'client_id'  => $clientId,
                'token'      => $token,
                'expires_at' => $expiresAt,
            ]);

            $responsePayload = [
                'responseCode'    => '2007300',
                'responseMessage' => 'Successful',
                'accessToken'     => $token,
                'tokenType'       => 'BearerToken',
                'expiresIn'       => $timeoutToken * 60, // convert ke detik
            ];

            $response = response()->json($responsePayload, 200);

            $this->bniLogResponse($request, 'auth-token.success', [
                'tenant_id' => $tenantId,
                'client_id' => $clientId,
                'response' => $this->bniMaskData($responsePayload),
            ], $startedAt);

            return $response;
        } catch (\Throwable $e) {
            $this->bniLogException($request, 'auth-token.exception', $e, [
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
            $tenantModel = config('tenancy.tenant_model', null);

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
