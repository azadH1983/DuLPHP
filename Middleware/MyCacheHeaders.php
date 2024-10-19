<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\SetCacheHeaders;

class MyCacheHeaders extends SetCacheHeaders
{
    function timeToSeconds($input)
    {
        $time = substr($input, strpos($input, '=') + 1);
        $unit = substr($time, -1); // Get the last character
        $value = (int)substr($time, 0, -1); // Get the numeric value without the last character

        return match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            'w' => $value * 604800,
            default => 0,
        };
    }

    public function handle($request, $next, $options = [])
    {
        if (is_string($options)) {
            $newOptions = 'public;max_age=' . $this->timeToSeconds($options) . ';etag';
        }
        return parent::handle($request, $next, $newOptions);
    }
}
