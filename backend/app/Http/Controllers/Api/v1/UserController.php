<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Resources\MeResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * JSON API controller for user listing, lookup, deletion, and the legacy
 * authenticated profile endpoint.
 */
class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Return all users.
     */
    public function index()
    {
        $this->authorize('user.manage');

        $users = User::all();

        return ApiResponse::success(
            'User list retrieved successfully',
            ['users' => $users]
        );
    }

    /**
     * Return only users who have both Discord and RSI verification completed.
     */
    public function verified()
    {
        $this->authorize('user.manage');

        $users = User::whereNotNull('rsi_verified_at')
            ->whereNotNull('discord_id')
            ->get();

        return ApiResponse::success(
            'Verified users retrieved successfully',
            ['users' => $users]
        );
    }

    /**
     * Return users missing either RSI verification or a Discord identity.
     */
    public function unverified()
    {
        $this->authorize('user.manage');

        $users = User::whereNull('rsi_verified_at')
            ->orWhereNull('discord_id')
            ->get();

        return ApiResponse::success(
            'Unverified users retrieved successfully',
            ['users' => $users]
        );
    }

    /**
     * Show a single user by Discord id.
     */
    public function show($discord_id)
    {
        $this->authorize('user.manage');

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
     * Delete a user by Discord id.
     */
    public function destroy($discord_id)
    {
        $this->authorize('user.manage');

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
     * Return the authenticated user's profile through the legacy `/profile`
     * endpoint.
     *
     * This endpoint stays available for backward compatibility while pointing
     * callers at `/api/v1/me` as the preferred replacement.
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

        $user->loadMissing('roles');
        $me = (new MeResource($user))->resolve($request);

        return ApiResponse::success(
            'Profile fetched successfully',
            [
                'user' => [
                    'id'                 => $me['id'],
                    'discord_id'         => $me['discord_id'],
                    'discord_name'       => $me['discord_name'],
                    'discord_avatar'     => $me['discord_avatar'],
                    'rsi_handle'         => $me['rsi_handle'],
                    'verified_rsi'       => $me['rsi_verified'],
                    'verified_discord'   => ! is_null($me['discord_id']),
                    'rank'               => $me['rank'],
                    'rank_level'         => $me['rank_level'],
                    'rank_name'          => $me['rank_name'],
                    'joined_at'          => $user->created_at,
                    'last_updated'       => $user->updated_at,
                ],
            ]
        )->header('X-Deprecated-Endpoint', '/api/v1/profile; use /api/v1/me');
    }
}
