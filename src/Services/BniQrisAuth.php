<?php

namespace ESolution\BNIPayment\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BniQrisAuth
{


    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: (config('bri.qris') ?? []);
    }
    /**
     * Ambil Access Token B2B + cache di Laravel.
     *
     * - Hit API: ../{version}/access-token/b2b
     * - Body: { "grantType": "client_credentials" }
     * - Header:
     *   - Content-Type: application/json
     *   - X-TIMESTAMP: yyyy-MM-dd'T'HH:mm:ssXXX
     *   - X-CLIENT-KEY: client_id
     *   - X-SIGNATURE: SHA256withRSA(privateKey, clientId + "|" + X-TIMESTAMP)
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        $config = $this->config;
        $cacheKey = 'bni_qirs_access_token_'. ($config['client_id']??'1');

        if (! $forceRefresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && !empty($cached['token']) && !empty($cached['expired_at'])) {
                // kasih buffer 30 detik agar tidak tepat di batas expiry
                if ($cached['expired_at'] > now()->addSeconds(30)->timestamp) {
                    return $cached['token'];
                }
            }
        }
            // dd('masuk sini');

        $data = self::requestAccessToken();

        $accessToken = $data['accessToken'] ?? null;
        $expiresIn   = (int) ($data['expiresIn'] ?? 900); // 900 = 15 menit

        if (! $accessToken) {
            throw new \RuntimeException('BNI QRIS: accessToken kosong, cek response / konfigurasi.');
        }

        $ttl = max(60, $expiresIn - 60); // buffer 1 menit
        Cache::put(
            $cacheKey,
            [
                'token'      => $accessToken,
                'expired_at' => now()->addSeconds($ttl)->timestamp,
            ],
            $ttl
        );

        return $accessToken;
    }

    /**
     * Hit endpoint Access Token (tanpa cache).
     */
    protected  function requestAccessToken(): array
    {
        $timestamp = now()->format('Y-m-d\TH:i:sP');

        $snapConfig = $this->config;
        $clientId   = $snapConfig['client_id'] ?? '';

        $stringToSign = $clientId . '|' . $timestamp;
        $signature    = self::signWithPrivateKey($stringToSign);

        $headers = [
            'Content-Type'  => 'application/json',
            'X-TIMESTAMP'   => $timestamp,
            'X-CLIENT-KEY'  => $clientId,
            'X-SIGNATURE'   => $signature,
        ];

        $version = trim($snapConfig['version'] ?? 'v1.0', '/');
        $path    = $snapConfig['path_access_token'] ?? '/access-token/b2b';

        $baseUrl = ($snapConfig && $snapConfig['debug'])
            ? ($snapConfig['base_url_staging'] ?? $snapConfig['base_url'] ?? '')
            : ($snapConfig['base_url'] ?? '');

        $url = rtrim($baseUrl, '/') . '/' . $version . $path;
        $response = Http::withHeaders($headers)
            ->timeout($snapConfig['timeout'] ?? config('bni.timeout'))
            // ->withOptions(['verify' => $snapConfig['verify_ssl'] ?? config('bni.verify_ssl')])
            ->post($url, [
                'grantType' => 'client_credentials',
            ]);

        $json = $response->json() ?? [];

        if (! $response->successful()) {
            throw new \RuntimeException(
                'BNI QRIS Access Token error: HTTP ' . $response->status() . ' ' . json_encode($json)
            );
        }

        return $json;
    }

    /**
     * Build X-SIGNATURE untuk semua API SNAP selain Access Token.
     *
     * Mengikuti dokumen:
     * - signature_type = 1 (default):
     *   stringToSign = HTTPMethod + ":" + EndpointUrl + ":" + AccessToken
     *                  + ":" + Lowercase(HexEncode(SHA-256(minify(RequestBody))))
     *                  + ":" + TimeStamp
     *   X-SIGNATURE = HMAC_SHA512(clientSecret, stringToSign)
     *
     * - signature_type = 2:
     *   stringToSign = HTTPMethod + ":" + EndpointUrl
     *                  + ":" + Lowercase(HexEncode(SHA-256(minify(RequestBody))))
     *                  + ":" + TimeStamp
     *   X-SIGNATURE = SHA256withRSA(privateKey, stringToSign) (base64)
     *
     * @param  string      $httpMethod   e.g. POST
     * @param  string      $endpointUrl  relative path SNAP, contoh: /v1.0/debit/payment-qr/qr-mpm
     * @param  array       $body         request payload
     * @param  string      $timestamp    X-TIMESTAMP
     * @param  string|null $accessToken  accessToken (hanya dipakai kalau signature_type = 1)
     * @return string
     */
    public  function buildRequestSignature(
        string $httpMethod,
        string $endpointUrl,
        array $body,
        string $timestamp,
        ?string $accessToken = null
    ): string {
        $httpMethod  = strtoupper($httpMethod);
        $endpointUrl = $endpointUrl ?: '/';

        $snapConfig    = $this->config;
        $signatureType = (int) ($snapConfig['signature_type'] ?? 1);

        // minify(RequestBody): cukup json_encode tanpa spasi
        $bodyString = empty($body) ? '' : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $bodyHash   = strtolower(hash('sha256', $bodyString));
// dd($bodyString);
        if ($signatureType === 1) {
            // Symmetric Signature + Access Token
            $clientSecret = $snapConfig['client_secret'] ?? '';
            if ($accessToken === null) {
                throw new \RuntimeException('BNI QRIS: accessToken wajib diisi untuk signature_type = 1');
            }

            $stringToSign = implode(':', [
                $httpMethod,
                $endpointUrl,
                $accessToken,
                $bodyHash,
                $timestamp,
            ]);
            // dd( $stringToSign);

            return base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true));

        }

        // Asymmetric Signature (RSA) tanpa Access Token
        $stringToSign = implode(':', [
            $httpMethod,
            $endpointUrl,
            $bodyHash,
            $timestamp,
        ]);

        return self::signWithPrivateKey($stringToSign);
    }

    /**
     * Helper RSA SHA256withRSA (base64-encoded).
     */
    public  function signWithPrivateKey(string $stringToSign): string
    {
        $path = $this->config['private_key_path']??null;

        if (! $path || ! file_exists($path)) {
            throw new \RuntimeException("BNI QRIS: private key tidak ditemukan di path: {$path}");
        }

        $pem = file_get_contents($path);
        $key = openssl_pkey_get_private($pem);

        if (! $key) {
            throw new \RuntimeException("BNI QRIS: private key invalid, cek file: {$path}");
        }

        openssl_sign($stringToSign, $rawSignature, $key, OPENSSL_ALGO_SHA256);

        return base64_encode($rawSignature);
    }
}
