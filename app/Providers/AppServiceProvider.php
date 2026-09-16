<?php

namespace App\Providers;

use App\Models\User;
use App\Services\SecurityService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12));

        Event::listen(Failed::class, function (Failed $event): void {
            $actor = isset($event->credentials['email'])
                ? User::where('email', $event->credentials['email'])->first()
                : null;

            app(SecurityService::class)->record($actor, 'failed_login', [
                'email' => $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
            ]);
        });

        Event::listen(Lockout::class, function (Lockout $event): void {
            app(SecurityService::class)->record(null, 'account_locked', [
                'ip' => request()->ip(),
            ]);
        });
    }
}
