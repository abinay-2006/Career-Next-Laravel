<?php

namespace App\Services;

use App\Models\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as SystemLog;

class ActivityLogger
{
    public function record(string $action, string $targetType, int|string|null $targetId = null, ?int $jobId = null, array $metadata = []): void
    {
        try {
            $actorGuard = collect(['admin', 'employer', 'jobseeker'])->first(fn ($guard) => Auth::guard($guard)->check());
            $actor = $actorGuard ? Auth::guard($actorGuard)->user() : null;
            Log::create([
                'JobId' => $jobId,
                'action' => $action,
                'cdate' => now(),
                'actor_type' => $actorGuard,
                'actor_id' => $actor?->getKey(),
                'target_type' => $targetType,
                'target_id' => $targetId,
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            ]);
        } catch (\Throwable $exception) {
            SystemLog::warning('Optional activity log write failed', ['exception' => $exception::class, 'action' => $action]);
        }
    }
}
