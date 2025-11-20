<?php

namespace App\Traits;

use App\Models\FailedAttempt;
use Carbon\Carbon;

trait HandlesFailedAttempts
{
    // Check if request is currently locked out
    public function isLockedOut(string $action, ?int $userId = null, ?string $ip = null)
    {
        $ip ??= request()->ip();

        $record = FailedAttempt::where('action', $action)
            ->where(function ($q) use ($userId, $ip) {
                if ($userId) {
                    $q->where('user_id', $userId);
                } else {
                    $q->where('ip_address', $ip);
                }
            })
            ->first();

        if (!$record) {
            return false;
        }

        if ($record->locked_until && $record->locked_until->isFuture()) {
            return $record->locked_until;
        }

        return false;
    }

    // Record a failure
    public function recordFailure(string $action, ?int $userId = null, ?string $ip = null)
    {
        $ip ??= request()->ip();

        $record = FailedAttempt::where('action', $action)
            ->where(function ($q) use ($userId, $ip) {
                if ($userId) {
                    $q->where('user_id', $userId);
                } else {
                    $q->where('ip_address', $ip);
                }
            })
            ->first();

        if (!$record) {
            $record = FailedAttempt::create([
                'action' => $action,
                'user_id' => $userId,
                'ip_address' => $ip,
                'attempts' => 1,
            ]);

            return $record;
        }

        $record->attempts += 1;

        // Lockout rule: 5 failures → 10-minute lockout
        if ($record->attempts >= 5) {
            $record->locked_until = Carbon::now()->addMinutes(10);
            $record->attempts = 0; // reset attempts after lockout
        }

        $record->save();

        return $record;
    }

    // Clear failure records after success
    public function clearFailures(string $action, ?int $userId = null, ?string $ip = null)
    {
        $ip ??= request()->ip();

        FailedAttempt::where('action', $action)
            ->where(function ($q) use ($userId, $ip) {
                if ($userId) {
                    $q->where('user_id', $userId);
                } else {
                    $q->where('ip_address', $ip);
                }
            })
            ->delete();
    }
}
