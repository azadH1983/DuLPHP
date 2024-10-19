<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class Localization
{
    private $supportedLocales = ['en', 'ar', 'dan', 'de', 'sin'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = ($request->hasHeader('Accept-Language')) ? $request->header("Accept-Language") : App::getLocale();
        if (!in_array($locale, $this->supportedLocales)) {
            $locale = 'en';
        }
        App::setLocale($locale);
        return $next($request);
    }
}
