<?php

namespace App\Jobs;

use App\Models\DriverProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireDriverTokensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Revoke all API tokens for suspended or inactive drivers.
     * Runs nightly — ensures suspended drivers can't keep using old tokens.
     */
    public function handle(): void
    {
        $suspended = DriverProfile::whereIn('status', ['suspended'])
            ->with('user')
            ->get();

        $revokedCount = 0;

        foreach ($suspended as $profile) {
            if (!$profile->user) continue;

            $count = DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->where('tokenable_id', $profile->user_id)
                ->delete();

            $revokedCount += $count;
        }

        Log::info('ExpireDriverTokensJob: completed', [
            'suspended_drivers' => $suspended->count(),
            'tokens_revoked'    => $revokedCount,
        ]);
    }
}
