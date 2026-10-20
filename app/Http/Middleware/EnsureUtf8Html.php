<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUtf8Html
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $contentType = (string) $response->headers->get('Content-Type', '');

        if (str_starts_with(strtolower($contentType), 'text/html')) {
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        }

        return $response;
    }
}
