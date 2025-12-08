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

    protected function endpoint(string $path): string
    {
        if ($this->debug) {
            $host = config('bni.hostname_staging');
        } else {
            $host = config('bni.hostname');
        }

        $port = (int) config('bni.port');
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

    protected function request(string $method, string $path, array $payload, $clientId = '', $prefix = '', $secret = ''): array
    {
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
            'expired_at' => $payload['datetime_expired'] ? date('Y-m-d H:i:s', strtotime($payload['datetime_expired'])) : null,
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
}
