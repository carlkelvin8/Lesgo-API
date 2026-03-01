<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ThrottleRequestsException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        AuthenticationException::class,
        AuthorizationException::class,
        ValidationException::class,
        ThrottleRequestsException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        // Always return JSON for API requests
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions with consistent JSON format.
     */
    protected function handleApiException(Request $request, Throwable $e): JsonResponse
    {
        $statusCode = 500;
        $message = 'An error occurred while processing your request.';
        $errors = null;

        // Authentication Exception
        if ($e instanceof AuthenticationException) {
            $statusCode = 401;
            $message = 'Unauthenticated. Please log in.';
        }
        // Authorization Exception
        elseif ($e instanceof AuthorizationException) {
            $statusCode = 403;
            $message = 'You are not authorized to perform this action.';
        }
        // Validation Exception
        elseif ($e instanceof ValidationException) {
            $statusCode = 422;
            $message = 'The given data was invalid.';
            $errors = $e->errors();
        }
        // Model Not Found
        elseif ($e instanceof ModelNotFoundException) {
            $statusCode = 404;
            $message = 'Resource not found.';
        }
        // Not Found Exception
        elseif ($e instanceof NotFoundHttpException) {
            $statusCode = 404;
            $message = 'The requested endpoint does not exist.';
        }
        // Throttle Exception
        elseif ($e instanceof ThrottleRequestsException) {
            $statusCode = 429;
            $message = 'Too many requests. Please slow down.';
        }
        // HTTP Exception
        elseif ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
            $message = $e->getMessage() ?: $message;
        }
        // Generic Exception
        else {
            // In production, don't leak error details
            if (!config('app.debug')) {
                $message = 'An unexpected error occurred. Please try again later.';
            } else {
                $message = $e->getMessage();
            }
        }

        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        // Add trace in debug mode only
        if (config('app.debug') && !in_array($statusCode, [401, 403, 404, 422, 429])) {
            $response['trace'] = $e->getTraceAsString();
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse|\Illuminate\Http\Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please log in.',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}
