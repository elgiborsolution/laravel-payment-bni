<?php

namespace ESolution\BNIPayment\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ESolution\BNIPayment\Models\BniPaymentLog;
use ESolution\BNIPayment\Exceptions\BniApiException;
use ESolution\BNIPayment\Enums\BniCode;

abstract class BaseClient
{
    protected string $channel;

    protected function endpoint(string $path): string
    {
        $host = config('bni.hostname');
        $port = (int) config('bni.port');
        $scheme = $port === 443 ? 'https' : 'http';
        return sprintf('%s://%s:%d%s', $scheme, $host, $port, $path);
    }

    protected function headers(): array
    {
        return [
            'Origin' => config('bni.origin'),
            'Content-Type' => 'application/json',
        ];
    }

    protected function request(string $method, string $path, array $payload): array
    {
        $url = $this->endpoint($path);
        $correlationId = (string) Str::uuid();

        $log = BniPaymentLog::create([
            'channel' => $this->channel,
            'request_payload' => $payload,
            'reff_id' => $correlationId,
            'ip' => request()?->ip()
        ]);

        $response = Http::withHeaders($this->headers())
            ->timeout(config('bni.timeout'))
            ->withOptions(['verify' => config('bni.verify_ssl')])
            ->send($method, $url, ['json' => $payload]);

        $body = [];
        try {
            $body = $response->json() ?? [];
        } catch (\Throwable $e) {
        }

        $log->update([
            'http_status' => $response->status(),
            'response_body' => $body,
            'bni_status' => $body['status'] ?? null,
            'bni_code' => $body['status'] ?? null,
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
        }

        return $body;
    }
}
