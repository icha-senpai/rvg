<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;

class RSIVerificationController extends Controller
{
    public function verify(Request $request)
    {
        // 1) Validate inputs
        $request->validate([
            'discord_id' => 'required',
            'rsi_handle' => 'required',
        ]);

        // 2) Find user by Discord ID
        $user = User::where('discord_id', $request->discord_id)->first();

        if (! $user) {
            return ApiResponse::error('User not found', [], 404);
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
 |----------------------------------------------------------------------
 | ORG EXTRACTION (bulletproof for all RSI layouts)
 |----------------------------------------------------------------------
 |
 | Searches multiple patterns because RSI can't keep consistency.
 |
*/

        // Pattern A: sidebar org link (your profile uses this)
        if (preg_match('/href="\/orgs\/([A-Z0-9]+)"/i', $html, $orgMatch)) {
            $orgCode = strtoupper($orgMatch[1]);
        }
        // Pattern B: /en/orgs/XYZ format
        elseif (preg_match('/href="\/en\/orgs\/([A-Z0-9]+)"/i', $html, $orgMatch)) {
            $orgCode = strtoupper($orgMatch[1]);
        }
        // Pattern C: Organization section (old layout)
        elseif (preg_match('/\/en\/orgs\/([A-Z0-9]{2,20})/i', $html, $orgMatch)) {
            $orgCode = strtoupper($orgMatch[1]);
        }
        else {
            return ApiResponse::error('No org membership found on RSI profile.', [], 400);  
        }

        // Required org check
        if ($orgCode !== 'XVILEGION') {
            return ApiResponse::error('User is not part of the required org.', [
                'found_org' => $orgCode,
                'required_org' => 'XVILEGION'
            ], 403);
        }


        /*
        |--------------------------------------------------------------------------
        | CODE CHECK: just search the entire HTML for the verification code
        --------------------------------------------------------------------------
        */
        if (! str_contains($html, $user->verification_code)) {
            return ApiResponse::error('Verification code not found anywhere on profile.', [], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFIED SUCCESSFULLY
        |--------------------------------------------------------------------------
        */
        $user->rsi_handle      = $request->rsi_handle;
        $user->rsi_verified_at = now();
        $user->save();

        return ApiResponse::success('User verified successfully', [
            'discord_id'  => $user->discord_id,
            'rsi_handle'  => $user->rsi_handle,
            'org'         => 'XVILEGION',
            'verified_at' => $user->rsi_verified_at,
        ]);
    }
}
