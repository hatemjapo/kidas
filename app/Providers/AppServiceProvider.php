<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->limitWritingRequests('writes', (int) config('booking.writes_per_minute'));
        $this->limitWritingRequests('warehouse', (int) config('booking.warehouse_actions_per_minute'));
    }

    private function limitWritingRequests(string $name, int $perMinute): void
    {
        RateLimiter::for($name, function (Request $request) use ($perMinute): Limit {
            $user = $request->user();

            return Limit::perMinute($perMinute)->by($user instanceof User ? (string) $user->id : (string) $request->ip());
        });
    }
}
