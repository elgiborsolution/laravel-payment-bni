<?php

namespace ESolution\BNIPayment\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

trait LogsBniControllerActivity
{
    protected function bniLoggingEnabled(): bool
    {
        return (bool) config('bni.logging.enabled', false);
    }

    protected function bniLogRequest(Request $request, string $event, array $context = []): void
    {
        $this->bniLog('info', $event, $request, $context);
    }

    protected function bniLogResponse(Request $request, string $event, array $context = [], ?float $startedAt = null): void
    {
        if ($startedAt !== null) {
            $context['execution_ms'] = round((microtime(true) - $startedAt) * 1000, 2);
        }

        $this->bniLog('info', $event, $request, $context);
    }

    protected function bniLogException(Request $request, string $event, Throwable $e, array $context = [], ?float $startedAt = null): void
    {
        $context['exception'] = [
            'type' => $e::class,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        if ($startedAt !== null) {
            $context['execution_ms'] = round((microtime(true) - $startedAt) * 1000, 2);
        }

        $this->bniLog('error', $event, $request, $context);
    }

    protected function bniLog(string $level, string $event, Request $request, array $context = []): void
    {
        if (! $this->bniLoggingEnabled()) {
            return;
        }

        Log::log($level, '[BNI Payment] ' . $event, array_merge($this->bniBaseContext($request), $context));
    }

    protected function bniBaseContext(Request $request): array
    {
        return [
            'controller' => static::class,
            'method' => $request->method(),
            'endpoint' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'headers' => $this->bniMaskData($request->headers->all()),
            'request' => $this->bniMaskData($request->all()),
        ];
    }

    protected function bniMaskData(mixed $data, array $sensitiveKeys = []): mixed
    {
        $sensitiveKeys = array_map('strtolower', array_merge($sensitiveKeys, [
            'authorization',
            'x-signature',
            'signature',
            'access_token',
            'accesstoken',
            'token',
            'client_secret',
            'secret',
            'password',
            'private_key',
            'public_key',
        ]));

        if (! is_array($data)) {
            return $data;
        }

        $masked = [];

        foreach ($data as $key => $value) {
            $keyName = strtolower((string) $key);

            if (in_array($keyName, $sensitiveKeys, true)) {
                $masked[$key] = $this->bniMaskValue($value);
                continue;
            }

            $masked[$key] = is_array($value)
                ? $this->bniMaskData($value, $sensitiveKeys)
                : $value;
        }

        return $masked;
    }

    protected function bniMaskValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_fill_keys(array_keys($value), '***');
        }

        if (is_string($value) && $value !== '') {
            return '***';
        }

        return '***';
    }
}
