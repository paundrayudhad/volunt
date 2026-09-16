<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Request-ID') ?: (string) Str::uuid();
        $request->headers->set('X-Request-ID', $id);

        return $next($request)->header('X-Request-ID', $id);
    }
}
