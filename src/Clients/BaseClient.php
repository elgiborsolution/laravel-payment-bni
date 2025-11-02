<?php

namespace ESolution\BNIPayment\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ESolution\BNIPayment\Models\BniApiCall;
use ESolution\BNIPayment\Exceptions\BniApiException;
use ESolution\BNIPayment\Enums\BniCode;

abstract class BaseClient
{
    protected string $channel;

    protected function endpoint(string $path): string
    {
        $host = bni_config('hostname');
        $port = (int) bni_config('port');
        $scheme = $port === 443 ? 'https' : 'http';
        return sprintf('%s://%s:%d%s', $scheme, $host, $port, $path);
    }

    protected function headers(): array
    {
        return [
            'Origin' => bni_config('origin'),
            'Content-Type' => 'application/json',
        ];
    }

    protected function request(string $method, string $path, array $payload): array
    {
        $url = $this->endpoint($path);
        $correlationId = (string) Str::uuid();

        $log = BniApiCall::create([
            'channel' => $this->channel,
            'endpoint' => $path,
            'method' => strtoupper($method),
            'request_body' => $payload,
            'correlation_id' => $correlationId,
            'ip' => request()?->ip(),
            'user_id' => auth()->id() ?? null,
        ]);

        $response = Http::withHeaders($this->headers())
            ->timeout(bni_config('timeout'))
            ->withOptions(['verify' => bni_config('verify_ssl')])
            ->send($method, $url, ['json' => $payload]);

        $body = [];
        try { $body = $response->json() ?? []; } catch (\Throwable $e) {}

        $log->update([
            'http_status' => $response->status(),
            'response_body' => $body,
            'bni_status' => $body['status'] ?? null,
            'bni_code' => $body['status'] ?? null,
        ]);

        if (! $response->successful()) {
            throw new BniApiException(
                'HTTP error from BNI API',
                (string) ($body['status'] ?? 'HTTP_'.$response->status()),
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
