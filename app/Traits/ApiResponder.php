<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Respuestas JSON estandarizadas para la API móvil.
 *
 * Formato exitoso con datos:  { "message": "...", "data": {...|[...]} }
 * Formato exitoso sin datos:  { "message": "..." }
 * Formato solo datos (GET):   { "data": {...|[...]} }
 * Formato de error:           { "message": "..." }
 * Formato de validación:      { "message": "...", "errors": {...} }
 */
trait ApiResponder
{
    /** GET — devuelve solo datos, sin mensaje. */
    protected function ok(mixed $data): JsonResponse
    {
        return response()->json(['data' => $data]);
    }

    /** POST exitoso (201) — mensaje + datos opcionales. */
    protected function created(string $message, mixed $data = null): JsonResponse
    {
        $payload = ['message' => $message];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        return response()->json($payload, 201);
    }

    /** Acción exitosa (200) — mensaje + datos opcionales. */
    protected function success(string $message, mixed $data = null): JsonResponse
    {
        $payload = ['message' => $message];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        return response()->json($payload);
    }

    /** Error genérico con status code explícito. */
    protected function error(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    protected function notFound(string $message = 'Recurso no encontrado.'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function forbidden(string $message = 'No tienes permiso para realizar esta acción.'): JsonResponse
    {
        return $this->error($message, 403);
    }

    protected function conflict(string $message): JsonResponse
    {
        return $this->error($message, 409);
    }

    protected function unprocessable(string $message): JsonResponse
    {
        return $this->error($message, 422);
    }

    protected function serverError(string $message = 'Ocurrió un error interno. Inténtalo de nuevo.'): JsonResponse
    {
        return $this->error($message, 500);
    }
}
