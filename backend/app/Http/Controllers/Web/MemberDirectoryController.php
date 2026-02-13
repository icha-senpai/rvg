<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MemberDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $searchNeedle = $search !== '' ? '%'.mb_strtolower($search).'%' : null;
        $dbDriver = DB::connection()->getDriverName();

        $users = User::query()
            ->select(
                'id',
                'rsi_handle',
                'discord_name',
                'discord_avatar',
                'callsign',
                'timezone',
                'favorite_ships',
                'favorite_guns',
                'experience_ratings',
                'rank',
                'rank_level',
                'global_status'
            )
            ->with(['roles:id,name,slug'])
            ->where('global_status', User::STATUS_ACTIVE)
            ->when($searchNeedle, function ($query) use ($dbDriver, $search, $searchNeedle) {
                $query->where(function ($q) use ($dbDriver, $search, $searchNeedle) {
                    $q->whereRaw('LOWER(discord_name) LIKE ?', [$searchNeedle])
                        ->orWhereRaw('LOWER(rsi_handle) LIKE ?', [$searchNeedle]);

                    $q->orWhereRaw('LOWER(callsign) LIKE ?', [$searchNeedle])
                        ->orWhereRaw('LOWER(timezone) LIKE ?', [$searchNeedle]);

                    if ($dbDriver === 'pgsql') {
                        $q->orWhereRaw('CAST(favorite_ships AS TEXT) ILIKE ?', [$searchNeedle])
                            ->orWhereRaw('CAST(favorite_guns AS TEXT) ILIKE ?', [$searchNeedle])
                            ->orWhereRaw('CAST(experience_ratings AS TEXT) ILIKE ?', [$searchNeedle]);
                    } elseif ($dbDriver === 'sqlite') {
                        $q->orWhereRaw('LOWER(CAST(favorite_ships AS TEXT)) LIKE ?', [$searchNeedle])
                            ->orWhereRaw('LOWER(CAST(favorite_guns AS TEXT)) LIKE ?', [$searchNeedle])
                            ->orWhereRaw('LOWER(CAST(experience_ratings AS TEXT)) LIKE ?', [$searchNeedle]);
                    } else {
                        $q->orWhereRaw('LOWER(CAST(favorite_ships AS CHAR)) LIKE ?', [$searchNeedle])
                            ->orWhereRaw('LOWER(CAST(favorite_guns AS CHAR)) LIKE ?', [$searchNeedle])
                            ->orWhereRaw('LOWER(CAST(experience_ratings AS CHAR)) LIKE ?', [$searchNeedle]);
                    }

                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            })
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Member/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
