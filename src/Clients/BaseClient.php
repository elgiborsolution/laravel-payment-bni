<?php

namespace ESolution\BNIPayment\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ESolution\BNIPayment\Models\BniPaymentLog;
use ESolution\BNIPayment\Exceptions\BniApiException;
use ESolution\BNIPayment\Enums\BniCode;
use ESolution\BNIPayment\Services\BniEnc;

abstract class BaseClient
{
    protected string $channel;
    protected bool $debug = false;

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Build endpoint URL.
     *
     * - Untuk channel selain "qris" -> pakai hostname eCollection lama
     * - Untuk channel "qris" -> pakai base_url SNAP (apisnap)
     */
    protected function endpoint(string $path): string
    {
        if ($this->channel === 'qris') {
            $snap = config('bni.snap', []);

            $baseUrl = $this->debug
                ? ($snap['base_url_staging'] ?? $snap['base_url'] ?? '')
                : ($snap['base_url'] ?? '');

            return rtrim($baseUrl, '/') . $path;
        }

        // VA / channel lain pakai host lama
        $host = $this->debug ? config('bni.hostname_staging') : config('bni.hostname');

        $port   = (int) config('bni.port');
        $scheme = $port === 443 ? 'https' : 'http';

        return sprintf('%s://%s%s', $scheme, $host, $path);
    }

    protected function headers(): array
    {
        return [
            'Origin' => config('bni.origin'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Helper parsing datetime menjadi format DB standar.
     */
    protected function parseDateTime(?string $datetime): ?string
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            return date('Y-m-d H:i:s', strtotime($datetime));
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function request(string $method, string $path, array $payload, $clientId = '', $prefix = '', $secret = ''): array
    {
        if ($this->channel === 'qris') {
            return $this->snapRequest($method, $path, $payload, $clientId);
        }

        $url = $this->endpoint($path);
        $correlationId = (string) Str::uuid();

        $log = BniPaymentLog::create([
            'client_id' => $clientId,
            'channel' => $this->channel,
            'amount' => $payload['amount'] ?? 0,
            'customer_name' => $payload['customer_name'] ?? null,
            'customer_no' => $payload['customer_phone'] ?? null,
            'request_payload' => $payload,
            'reff_id' => $payload['reff_id'] ?? null,
            'expired_at' => !empty($payload['datetime_expired']) ? date('Y-m-d H:i:s', strtotime($payload['datetime_expired'])) : null,
            'ip' => request()?->ip()
        ]);

        $encPayload = BniEnc::encrypt($payload, $clientId, $secret);

        $response = Http::withHeaders($this->headers())
            ->timeout(config('bni.timeout'))
            ->withOptions(['verify' => config('bni.verify_ssl')])
            ->send(
                $method,
                $url,
                [
                    'json' => [
                        'client_id' => $clientId,
                        'prefix' => $prefix,
                        'data' => $encPayload
                    ]
                ]
            );

        $body = [];
        try {
            $body = $response->json() ?? [];
        } catch (\Throwable $e) {
        }

        $log->update([
            'response_payload' => $body,
            'status' => $body['status'] ?? null
        ]);

        if (! $response->successful()) {
            throw new BniApiException(
                'HTTP error from BNI API',
                (string) ($body['status'] ?? 'HTTP_' . $response->status()),
                $body,
                ['channel' => $this->channel, 'endpoint' => $path, 'correlation_id' => $correlationId]
            );
        }

        if (isset($body['status']) && $body['status'] !== BniCode::SUCCESS->value) {
            $code = $body['status'];
            throw new BniApiException(
                BniCode::describe($code),
                $code,
                $body,
                ['channel' => $this->channel, 'endpoint' => $path, 'correlation_id' => $correlationId]
            );
        } else {
            $body = BniEnc::decrypt($body['data'], $clientId, $secret);
        }

        return $body;
    }

    /**
     * Request untuk SNAP (channel qris).
     *
     * Implementasi:
     * - X-TIMESTAMP
     * - Access Token B2B (grantType=client_credentials) via BniSnapAuth::getAccessToken()
     * - X-SIGNATURE (HMAC/RSA) via BniSnapAuth::buildRequestSignature()
     * - Authorization: Bearer {accessToken} (jika signature_type = 1)
     */
    protected function snapRequest(
        string $method,
        string $path,
        array $payload,
        $clientId = ''
    ): array {
        $snapConfig    = config('bni.snap', []);
        $method        = strtoupper($method);
        $endpointUrl   = $path; // relative path SNAP, ex: /v1.0/debit/payment-qr/qr-mpm
        $url           = $this->endpoint($path);
        $timestamp     = now()->format('Y-m-d\TH:i:sP');
        $signatureType = (int) ($snapConfig['signature_type'] ?? 1);
        $correlationId = (string) Str::uuid();

        // generate external id untuk X-EXTERNAL-ID (unik per request)
        $externalId = (string) Str::uuid();

        // log request terlebih dahulu
        $amount = 0;
        if (isset($payload['amount']['value'])) {
            $amount = (float) $payload['amount']['value'];
        }

        $log = BniPaymentLog::create([
            'client_id'       => $clientId ?: ($snapConfig['client_id'] ?? ''),
            'channel'         => $this->channel,
            'amount'          => $amount,
            'customer_name'   => null,
            'customer_no'     => null,
            'invoice_no'      => $payload['additionalInfo']['invoiceNumber'] ?? null,
            'qris_content'    => null,
            'va_number'       => null,
            'status'          => null,
            'external_id'     => $externalId,
            'reff_id'         => $payload['partnerReferenceNo'] ?? null,
            'expired_at'      => !empty($payload['validityPeriod'])
                ? $this->parseDateTime($payload['validityPeriod'])
                : null,
            'request_payload' => $payload,
            'ip'              => request()?->ip(),
        ]);

        // Access Token (hanya kalau signature_type = 1 / Symmetric Signature)
        $accessToken = null;
        if ($signatureType === 1) {
            $accessToken = BniSnapAuth::getAccessToken();
        }

        // X-SIGNATURE
        $signature = BniSnapAuth::buildRequestSignature(
            $method,
            $endpointUrl,
            $payload,
            $timestamp,
            $accessToken
        );

        // Header SNAP
        $headers = [
            'Content-Type'   => 'application/json',
            'X-TIMESTAMP'    => $timestamp,
            'X-SIGNATURE'    => $signature,
            'X-CLIENT-KEY'   => $snapConfig['client_key'] ?? ($snapConfig['client_id'] ?? ''),
            'X-PARTNER-ID'   => $snapConfig['partner_id'] ?? '',
            'X-EXTERNAL-ID'  => $externalId,
        ];

        if (! empty($accessToken)) {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }

        if ($origin = config('bni.origin')) {
            $headers['Origin'] = $origin;
        }

        // kirim request ke SNAP
        $response = Http::withHeaders($headers)
            ->timeout($snapConfig['timeout'] ?? config('bni.timeout'))
            ->withOptions(['verify' => $snapConfig['verify_ssl'] ?? config('bni.verify_ssl')])
            ->send(
                $method,
                $url,
                empty($payload)
                    ? []
                    : ['json' => $payload]
            );

        $body = [];
        try {
            $body = $response->json() ?? [];
        } catch (\Throwable $e) {
        }

        $log->update([
            'response_payload' => $body,
            // untuk QRIS, kita pakai responseCode sebagai status (kalau ada)
            'status'           => $body['responseCode'] ?? null,
        ]);

        if (! $response->successful()) {
            throw new BniApiException(
                'HTTP error from BNI SNAP API',
                'HTTP_' . $response->status(),
                $body,
                [
                    'channel'        => $this->channel,
                    'endpoint'       => $path,
                    'correlation_id' => $correlationId,
                ]
            );
        }

        // Untuk QRIS SNAP, kita tidak decrypt apapun; langsung kembalikan body JSON
        return $body;
    }
}
