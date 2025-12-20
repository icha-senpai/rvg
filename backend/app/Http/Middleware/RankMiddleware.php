<?php

namespace App\Http\Middleware;

use Closure;
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

        $userRank = (int) $user->rank_level;
        $requiredRank = (int) $requiredRank;

        if ($userRank < $requiredRank) {
            return response()->json([
                'error' => 'Insufficient rank',
                'required_rank_level' => $requiredRank,
                'your_rank_level' => $userRank,
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
