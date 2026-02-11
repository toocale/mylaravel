<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckNotInstalled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if .env exists and APP_KEY is set, which usually indicates installed
        if (file_exists(base_path('.env')) && env('APP_KEY')) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
