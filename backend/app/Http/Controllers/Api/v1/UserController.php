<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Return all users.
     */
    public function index()
    {
        $users = User::all();

        return ApiResponse::success(
            'User list retrieved successfully',
            ['users' => $users]
        );
    }

    /**
     * Return all verified users.
     */
    public function verified()
    {
        $users = User::whereNotNull('rsi_verified_at')
            ->whereNotNull('discord_verified_at')
            ->get();

        return ApiResponse::success(
            'Verified users retrieved successfully',
            ['users' => $users]
        );
    }

    /**
     * Return all unverified users.
     */
    public function unverified()
    {
        $users = User::whereNull('rsi_verified_at')
            ->orWhereNull('discord_verified_at')
            ->get();

        return ApiResponse::success(
            'Unverified users retrieved successfully',
            ['users' => $users]
        );
    }

    /**
     * Show a single user by Discord ID.
     */
    public function show($discord_id)
    {
        $user = User::where('discord_id', $discord_id)->first();

        if (! $user) {
            return ApiResponse::error(
                'User not found',
                [],
                404
            );
        }

        return ApiResponse::success(
            'User retrieved successfully',
            ['user' => $user]
        );
    }

    /**
     * Delete a user by Discord ID.
     */
    public function destroy($discord_id)
    {
        $user = User::where('discord_id', $discord_id)->first();

        if (! $user) {
            return ApiResponse::error(
                'User not found',
                [],
                404
            );
        }

        $user->delete();

        return ApiResponse::success(
            'User deleted successfully',
            []
        );
    }

    /**
     * Return the authenticated user's profile.
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error(
                'Not authenticated',
                [],
                401
            );
        }

        return ApiResponse::success(
            'Profile fetched successfully',
            [
                'user' => [
                    'id'                 => $user->id,
                    'discord_id'         => $user->discord_id,
                    'discord_username'   => $user->discord_username,
                    'discord_global_name'=> $user->discord_global_name,
                    'discord_avatar'     => $user->discord_avatar,
                    'rsi_handle'         => $user->rsi_handle,
                    'rsi_org'            => $user->rsi_org,
                    'verified_rsi'       => (bool) $user->rsi_verified_at,
                    'verified_discord'   => (bool) $user->discord_verified_at,
                    'rank'               => $user->rank,
                    'rank_level'         => $user->rank_level,
                    'rank_name'          => $user->rank_name,
                    'joined_at'          => $user->created_at,
                    'last_updated'       => $user->updated_at,
                ],
            ]
        );
    }
}
