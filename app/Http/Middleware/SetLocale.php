<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['en', 'ru'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = $user !== null
            ? $user->locale
            : ($request->session()->get('locale') ?? $request->getPreferredLanguage(self::SUPPORTED));

        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'en');

        return $next($request);
    }
}
