<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Error message"),
 *     @OA\Property(property="data", type="object", nullable=true)
 * )
 * 
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     @OA\Property(property="current_page", type="integer"),
 *     @OA\Property(property="from", type="integer"),
 *     @OA\Property(property="last_page", type="integer"),
 *     @OA\Property(property="per_page", type="integer"),
 *     @OA\Property(property="to", type="integer"),
 *     @OA\Property(property="total", type="integer")
 * )
 * 
 * @OA\Schema(
 *     schema="PaginationLinks",
 *     @OA\Property(property="first", type="string"),
 *     @OA\Property(property="last", type="string"),
 *     @OA\Property(property="prev", type="string", nullable=true),
 *     @OA\Property(property="next", type="string", nullable=true)
 * )
 */
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
    /**
     * 200 success with data.
     * Paginated responses include a standardized `meta` + `links` block.
     */
    protected function success(mixed $data, string $message = 'Success', int $status = 200): JsonResponse
    {
        $payload = [
            'success'    => true,
            'message'    => $message,
            'request_id' => $this->requestId(),
        ];

        if ($data instanceof LengthAwarePaginator) {
            $payload['data']  = $data->items();
            $payload['meta']  = $this->paginationMeta($data);
            $payload['links'] = $this->paginationLinks($data);
        } else {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * 201 created.
     */
    protected function created(mixed $data, string $message = 'Created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * 200 with just a message (delete, logout, etc.).
     */
    protected function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'success'    => true,
            'message'    => $message,
            'request_id' => $this->requestId(),
        ], $status);
    }

    /**
     * 4xx / 5xx error.
     */
    protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success'    => false,
            'message'    => $message,
            'request_id' => $this->requestId(),
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Resolve the current request ID (set by RequestId middleware).
     */
    private function requestId(): string
    {
        return app()->bound('request_id') ? app('request_id') : '';
    }

    /**
     * Build the standardized pagination meta block.
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
            'has_more'     => $paginator->hasMorePages(),
        ];
    }

    /**
     * Build prev/next links for the paginator.
     */
    private function paginationLinks(LengthAwarePaginator $paginator): array
    {
        return [
            'first' => $paginator->url(1),
            'last'  => $paginator->url($paginator->lastPage()),
            'prev'  => $paginator->previousPageUrl(),
            'next'  => $paginator->nextPageUrl(),
        ];
    }
}
