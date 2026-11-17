<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $exception, \Illuminate\Http\Request $request) {
            if ($exception instanceof \Illuminate\Validation\ValidationException
                || $exception instanceof \Illuminate\Auth\AuthenticationException
                || $exception instanceof \Illuminate\Http\Exceptions\HttpResponseException) return null;
            $http=$exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
            $status=$http ? $exception->getStatusCode() : 500;
            $headers=$http ? $exception->getHeaders() : [];
            $message=$status>=500
                ? 'Layanan belum dapat digunakan. Silakan coba kembali atau hubungi pengelola.'
                : ($status===404 ? 'Halaman tidak ditemukan.' : ($exception->getMessage() ?: 'Permintaan tidak dapat diproses.'));
            $response=$request->expectsJson()
                ? response()->json(['message'=>$message],$status,$headers)
                : response()->view('errors.safe',['status'=>$status,'message'=>$message],$status,$headers);
            return $response->header('Cache-Control','no-store, private');
        });
    })->create();
