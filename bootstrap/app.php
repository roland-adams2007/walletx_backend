<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureJsonHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account.verified' => EnsureEmailIsVerified::class,
            'account.active' => EnsureAccountIsActive::class,
            'role' => CheckRole::class,
        ]);

        $middleware->api(prepend: [
            EnsureJsonHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });

        $exceptions->renderable(function (Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            $response = [
                'success' => false,
                'message' => 'Something went wrong.'
            ];
            $statusCode = 500;

            if ($e instanceof MethodNotAllowedHttpException) {
                $response['message'] = 'Method not allowed for this endpoint.';
                $statusCode = 405;
            } elseif ($e instanceof NotFoundHttpException) {
                $response['message'] = 'The requested endpoint was not found.';
                $statusCode = 404;
            } elseif ($e instanceof ThrottleRequestsException) {
                $response['message'] = 'Too many requests. Please try again later.';
                $response['retry_after'] = $e->getHeaders()['Retry-After'] ?? 60;
                $statusCode = 429;
            } elseif ($e instanceof ValidationException) {
                $response['message'] = 'The given data was invalid.';
                $response['errors'] = $e->errors();
                $statusCode = 422;
            } elseif ($e instanceof AuthenticationException) {
                $response['message'] = 'Unauthenticated. Please log in.';
                $statusCode = 401;
            } elseif ($e instanceof AuthorizationException) {
                $response['message'] = 'You are not authorized to perform this action.';
                $statusCode = 403;
            } elseif ($e instanceof ModelNotFoundException) {
                $response['message'] = 'The requested resource was not found.';
                $statusCode = 404;
            } elseif ($e instanceof QueryException) {
                if (app()->environment('production')) {
                    $response['message'] = 'An unexpected error occurred. Please try again later.';
                } else {
                    $response['message'] = 'Database error: ' . $e->getMessage();
                }
                $statusCode = 500;
            } else {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                if (app()->environment('production') && $statusCode === 500) {
                    $response['message'] = 'An unexpected error occurred. Please try again later.';
                } else {
                    $response['message'] = $e->getMessage() ?: 'Something went wrong.';
                }
            }

            return response()->json($response, $statusCode);
        });
    })->create();
