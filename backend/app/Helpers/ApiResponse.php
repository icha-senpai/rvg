<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success(
        ?string $message = null,
        mixed $data = null,
        int $status = 200
    ) {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public static function error(
        ?string $message = null,
        mixed $errors = null,
        int $status = 400
    ) {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    public static function noContent()
    {
        return response()->noContent();
    }
}
