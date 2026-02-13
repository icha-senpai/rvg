<?php

namespace App\Http\Middleware;

use Closure;
use App\Domain\AccessControl\RoleHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RankMiddleware
{
    public function handle(Request $request, Closure $next, $requiredRank)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        if ($user->hasRole('director') || $user->hasRole('tech_director')) {
            return $next($request);
        }

        $requiredRank = (int) $requiredRank;
        $requiredRole = match($requiredRank) {
            1 => 'member',
            2 => 'lieutenant',
            3 => 'commander',
            4 => 'wing_commander',
            5 => 'admiral',
            6 => 'grand_admiral',
            default => null,
        };

        if (! $requiredRole) {
            return response()->json([
                'error' => 'Invalid rank requirement',
                'required_rank_level' => $requiredRank,
            ], 500);
        }

        $user->loadMissing('roles:id,slug');

        if (! RoleHierarchy::userAtLeast($user, $requiredRole)) {
            return response()->json([
                'error' => 'Insufficient rank',
                'required_rank_level' => $requiredRank,
                'required_role' => $requiredRole,
                'message' => 'You need at least rank ' . $this->getRankName($requiredRank) . ' to access this resource.'
            ], 403);
        }

        return $next($request);
    }

    /**
     * Get rank name from rank level
     */
    private function getRankName(int $level): string
    {
        return match($level) {
            1 => 'Member',
            2 => 'Lieutenant',
            3 => 'Commander',
            4 => 'Wing Commander',
            5 => 'Admiral',
            6 => 'Grand Admiral',
            default => 'Unknown Rank'
        };
    }
}
