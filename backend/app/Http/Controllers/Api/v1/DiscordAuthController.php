<?php

namespace App\Http\Controllers\Api\V1;


use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Services\DiscordOAuthService;

class DiscordAuthController extends Controller
{
    protected $discord;

    public function __construct(DiscordOAuthService $discord)
    {
        $this->discord = $discord;
    }

    public function redirect()
    {
        return $this->discord->redirect();

    }

    public function callback()
    {
    try {
        // Fetch user from Discord via service
        $discordUser = $this->discord->getUser();
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'OAuth failed',
            'details' => $e->getMessage(),
        ], 400);
    }

    // Sync user using the service
    $user = $this->discord->syncBasicUser($discordUser);

    return ApiResponse::success('User authenticated successfully', $user);
    }

}
