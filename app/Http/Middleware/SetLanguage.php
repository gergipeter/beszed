<?php

namespace App\Http\Middleware;

use App\Services\LanguageService;
use Closure;
use Illuminate\Http\Request;

class SetLanguage
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $language = null;

        // 1. Check URL parameter (?lang=en)
        if ($request->has('lang')) {
            $language = $request->input('lang');
        }
        // 2. Check header (Accept-Language: en)
        elseif ($request->hasHeader('Accept-Language')) {
            $language = LanguageService::getBrowserLanguage();
        }
        // 3. Check user preference (if authenticated)
        elseif (auth()->check() && auth()->user()->language) {
            $language = auth()->user()->language;
        }
        // 4. Check session
        elseif (session()->has('language')) {
            $language = session()->get('language');
        }
        // 5. Use default
        else {
            $language = LanguageService::getDefaultLanguage();
        }

        // Validate and set language
        if (LanguageService::isSupported($language)) {
            session()->put('language', $language);
            LanguageService::setDefaultLanguage($language);
        }

        return $next($request);
    }
}
