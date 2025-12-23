<?php

namespace ESolution\BNIPayment\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class AuthTokenB2BController extends Controller
{
    
    public function handle(Request $request, $tenantId = null)
    {
        $clientId  = $request->header('X-CLIENT-KEY');
        $timestamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE');

        // Cek header wajib
        if (!$clientId || !$timestamp || !$signature) {
            return response()->json([
                'responseCode' => '4007300',
                'responseMessage' => 'Bad Request',
            ], 400);
        }

        // Regex untuk ISO 8601
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/';
        if (!preg_match($pattern, $timestamp)) {
            return response()->json([
                'responseCode' => '4007301',
                'responseMessage' => 'Invalid Field Format',
            ], 400);
        }

        // Ambil client
        $callbackConfigAll = config('bni.callback');

        if (empty($callbackConfigAll[$clientId])) {
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized Client',
            ], 401);
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
            if (!empty($publicKeyString)) {
                $key = openssl_pkey_get_public($publicKeyString);
                if ($key !== false) {
                    return $key;
                }
            }

            // 2. Fallback: cek file path
            if (!empty($publicKeyPath)) {
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
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized. [Invalid X-SIGNATURE]',
            ], 401);
        }

        // Generate token
        $token = Str::random(64);

        $tenant = $this->initializeTenantIfNeeded($tenantId);

        DB::table('bni_access_tokens')->insert([
            'client_id'  => $clientId,
            'token'      => $token,
            'expires_at' => now()->addHours(1),
        ]);

        return response()->json([
            'responseCode' => '2007300',
            'responseMessage' => 'Successful',
            'accessToken' => $token,
            'tokenType'   => 'BearerToken',
            'expiresIn'   => 3600,
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
