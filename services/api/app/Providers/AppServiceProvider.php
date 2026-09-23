<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return rtrim(config('app.url'), '/')
                .'/reset-password?token='.rawurlencode($token)
                .'&email='.rawurlencode($notifiable->getEmailForPasswordReset());
        });

        RateLimiter::for('graphql', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(240)->by('user:'.$request->user()->id)
                : Limit::perMinute(600)->by('ip:'.$request->ip());
        });
    }
}
