<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->renderable(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }
            $errors = $e->errors();
            $fields = is_array($errors) ? $errors : $errors->toArray();
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'type' => 'validation_error',
                    'message' => 'Validation failed',
                    'fields' => $fields,
                ],
            ], $e->status);
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'type' => 'authentication_error',
                    'message' => $e->getMessage() ?: 'Unauthenticated.',
                ],
            ], 401);
        });

        $this->renderable(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'type' => 'authorization_error',
                    'message' => $e->getMessage() ?: 'This action is unauthorized.',
                ],
            ], 403);
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'type' => 'not_found',
                    'message' => 'Resource not found.',
                ],
            ], 404);
        });

        $this->renderable(function (\JsonException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'type' => 'validation_error',
                    'message' => 'Invalid JSON in request body.',
                    'fields' => (object) [],
                ],
            ], 422);
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }
            $message = config('app.debug') ? $e->getMessage() : 'Server error.';
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'type' => 'server_error',
                    'message' => $message,
                ],
            ], 500);
        });
    }
}
