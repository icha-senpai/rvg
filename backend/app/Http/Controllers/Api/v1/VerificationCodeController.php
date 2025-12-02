<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;

class VerificationCodeController extends Controller
{
    public function generate(Request $request)
    {
        // User must already be authenticated
        $user = auth()->user();

        if (!$user) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        // Generate code like ABC-123
        $code = strtoupper(Str::random(3)) . '-' . rand(100, 999);

        $user->verification_code = $code;
        $user->verification_expires_at = now()->addMinutes(10);
        $user->save();

        return ApiResponse::success('Verification code generated successfully', [
            'verification_code' => $code,
            'expires_at' => $user->verification_expires_at,
        ]);
    }
}
