<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Running from a sub-folder URL (http://127.0.0.1/laravel/mailbatch): when APP_URL contains a path,
        // every generated link / redirect / asset URL keeps that path instead of falling back to the host root.
        $root = rtrim((string) config('app.url'), '/');
        if (! $this->app->runningInConsole() && (string) parse_url($root, PHP_URL_PATH) !== '') {
            URL::forceRootUrl($root);
        }

        Paginator::useBootstrapFive();

        Password::defaults(function () {
            $rule = Password::min(10)->letters()->mixedCase()->numbers();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });

        // SMTP tests open outbound connections: keep them slow and per user.
        RateLimiter::for('smtp-test', function (Request $request) {
            $key = 'smtp-test:'.($request->user()?->id ?: $request->ip());

            return [
                Limit::perMinute(6)->by($key),
                Limit::perHour(40)->by($key.':hour'),
            ];
        });

        // Admin settings override config/mailbatch.php. Long-running workers refresh before each job.
        $apply = function (): void {
            try {
                SystemSetting::applyToConfig();
            } catch (Throwable) {
                // table or cache not available yet (fresh install / before migrations)
            }
        };

        $apply();
        Queue::before($apply);
    }
}
