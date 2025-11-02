<?php

if (! function_exists('bni_config')) {
    function bni_config(string $key, $default = null) {
        return app('bni.config')->get($key, $default);
    }
}
