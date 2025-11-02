<?php

namespace ESolution\BNIPayment\Support;

use Illuminate\Support\Facades\Cache;
use ESolution\BNIPayment\Models\BniConfig;

class BniConfigManager
{
    protected string $cacheKey = 'bni_config_cache_v1';

    public function all(): array
    {
        return Cache::remember($this->cacheKey, 300, function () {
            return BniConfig::query()->pluck('value', 'key')->toArray();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        if (array_key_exists($key, $all)) {
            $val = $all[$key];
            $decoded = json_decode($val, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
        }
        return config('bni.'.$key, $default);
    }

    public function set(string $key, mixed $value, ?string $description = null): void
    {
        $stored = is_array($value) ? json_encode($value) : (string) $value;
        BniConfig::updateOrCreate(['key' => $key], ['value' => $stored, 'description' => $description]);
        Cache::forget($this->cacheKey);
    }

    public function clearCache(): void
    {
        Cache::forget($this->cacheKey);
    }
}
