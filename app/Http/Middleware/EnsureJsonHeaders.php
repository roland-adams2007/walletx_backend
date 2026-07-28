<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->accepts('application/json')) {
            return response()->json([
                'success' => false,
                'message' => 'The Accept header must be set to application/json.',
            ], 406);
        }

        if ($request->isMethod('post') || $request->isMethod('put') || $request->isMethod('patch')) {
            if (!$request->isJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The Content-Type header must be set to application/json.',
                ], 415);
            }
        }

        return $next($request);
    }
}