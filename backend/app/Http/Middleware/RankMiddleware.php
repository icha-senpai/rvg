<?php

namespace App\Http\Middleware;

use Closure;
use App\Domain\AccessControl\AccessService;
use Illuminate\Http\Request;

class RankMiddleware
{
    public function __construct(
        protected AccessService $access
    ) {}

    public function handle(Request $request, Closure $next, $requiredRank)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        if ($this->access->isDirectorLike($user)) {
            return $next($request);
        }

        $requiredRank = (int) $requiredRank;
        $requiredRole = match ($requiredRank) {
            1 => 'member',
            2 => 'lieutenant',
            3 => 'cit',
            4 => 'commander',
            5 => 'wing_commander',
            6 => 'admiral',
            7 => 'grand_admiral',
            default => null,
        };

        if (! $requiredRole) {
            return response()->json([
                'error' => 'Invalid rank requirement',
                'required_rank_level' => $requiredRank,
            ], 500);
        }

        if (! $this->access->atLeast($user, $requiredRole)) {
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
        return match ($level) {
            1 => 'Member',
            2 => 'Lieutenant',
            3 => 'C.I.T (Commander in Training)',
            4 => 'Commander',
            5 => 'Wing Commander',
            6 => 'Admiral',
            7 => 'Grand Admiral',
            default => 'Unknown Rank'
        };
    }
}
