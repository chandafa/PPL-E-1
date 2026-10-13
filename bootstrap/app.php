<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AuthenticateF1Token;
use App\Http\Middleware\ParseMultipartBody;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'f1.auth' => AuthenticateF1Token::class,
        ]);

        // PATCH multipart/form-data (API-KE01-11) tidak diurai PHP secara bawaan.
        $middleware->api(prepend: [ParseMultipartBody::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Kesalahan bisnis terstruktur (4xx) bukan kegagalan sistem: tidak perlu masuk log error.
        $exceptions->dontReport(ApiException::class);

        // Semua kesalahan API memakai amplop {code, message, data: null, errorCode}.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ApiException => ApiResponse::error($e->status, $e->errorCode, $e->getMessage()),
                $e instanceof AuthenticationException => ApiResponse::error(401, 'UNAUTHORIZED', 'Token tidak ada atau tidak sah'),
                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException => ApiResponse::error(403, 'FORBIDDEN', 'Pengguna sah, akses tidak cukup'),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error(404, 'NOT_FOUND', 'Sumber daya tidak ditemukan'),
                $e instanceof MethodNotAllowedHttpException => ApiResponse::error(405, 'METHOD_NOT_ALLOWED', 'Metode HTTP tidak diizinkan untuk sumber daya ini'),
                $e instanceof PostTooLargeException => ApiResponse::error(413, 'PAYLOAD_TOO_LARGE', 'Ukuran permintaan melebihi batas'),
                $e instanceof ThrottleRequestsException => ApiResponse::error(429, 'TOO_MANY_REQUESTS', 'Terlalu banyak permintaan'),
                $e instanceof ValidationException => ApiResponse::error(400, 'VALIDATION_ERROR', (string) collect($e->errors())->flatten()->first()),
                $e instanceof HttpExceptionInterface => ApiResponse::error($e->getStatusCode(), 'HTTP_ERROR', 'Permintaan tidak dapat diproses'),
                default => ApiResponse::error(500, 'INTERNAL_ERROR', 'Terjadi kesalahan pada server'),
            };
        });
    })->create();
