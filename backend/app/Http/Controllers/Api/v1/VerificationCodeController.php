<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;

class VerificationCodeController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'discord_id' => 'required'
        ]);

        $user = User::where('discord_id', $request->discord_id)->first();

        if (!$user) {
            return ApiResponse::error('User not found', [], 404);
        }

        // Generate code like ABC-123
        $code = strtoupper(Str::random(3)) . '-' . rand(100, 999);

        $user->verification_code = $code;
        $user->verification_expires_at = now()->addMinutes(10);
        $user->save();

        return ApiResponse::success('Verification code generated successfully', [
            'verification_code' => $code,
            'expires_at' => $user->verification_expires_at
        ]);
    }
}
