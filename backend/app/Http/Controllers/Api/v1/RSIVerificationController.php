<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;

class RSIVerificationController extends Controller
{
    public function verify(Request $request)
    {
        // 1) Validate inputs
        $request->validate([
            'rsi_handle' => 'required',
        ]);

        // 2) Use authenticated user (Sanctum)
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        // 3) Check expiration
        if ($user->verification_expires_at === null || now()->greaterThan($user->verification_expires_at)) {
            return ApiResponse::error('Verification code expired', [], 400);
        }

        // 4) Fetch RSI profile HTML
        $url = 'https://robertsspaceindustries.com/citizens/' . $request->rsi_handle;
        $response = Http::get($url);

        if ($response->failed()) {
            return ApiResponse::error('Failed to fetch RSI profile', [], 500);
        }

        $html = $response->body();

        /*
        |--------------------------------------------------------------------------
        | ORG EXTRACTION
        |--------------------------------------------------------------------------
        */

        // Pattern A: sidebar org link
        if (preg_match('/href="\/orgs\/([A-Z0-9]+)"/i', $html, $orgMatch)) {
            $orgCode = strtoupper($orgMatch[1]);
        }
        // Pattern B: /en/orgs/XYZ
        elseif (preg_match('/href="\/en\/orgs\/([A-Z0-9]+)"/i', $html, $orgMatch)) {
            $orgCode = strtoupper($orgMatch[1]);
        }
        // Pattern C: generic /en/orgs/XXX somewhere
        elseif (preg_match('/\/en\/orgs\/([A-Z0-9]{2,20})/i', $html, $orgMatch)) {
            $orgCode = strtoupper($orgMatch[1]);
        }
        else {
            return ApiResponse::error('No org membership found on RSI profile.', [], 400);
        }

        // Required org check
        if ($orgCode !== 'SRN') {
            return ApiResponse::error('User is not part of the required org.', [
                'found_org'    => $orgCode,
                'required_org' => 'SRN',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | CODE CHECK: verify the code is on the profile
        |--------------------------------------------------------------------------
        */
        if (! $user->verification_code || ! str_contains($html, $user->verification_code)) {
            return ApiResponse::error('Verification code not found anywhere on profile.', [], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFIED SUCCESSFULLY
        |--------------------------------------------------------------------------
        */
        $user->rsi_handle      = $request->rsi_handle;
        $user->rsi_verified_at = now();
        $user->global_status   = 'active';  // flip from pending → active
        $user->save();

        return ApiResponse::success('User verified successfully', [
            'discord_id'  => $user->discord_id,
            'rsi_handle'  => $user->rsi_handle,
            'org'         => 'SRN',
            'verified_at' => $user->rsi_verified_at,
        ]);
    }
}
