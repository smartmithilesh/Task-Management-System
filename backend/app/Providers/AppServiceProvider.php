<?php

namespace App\Providers;

use App\Services\Integrations\IntegrationAdapter;
use App\Services\Integrations\IntegrationRegistry;
use App\Services\Integrations\OAuthProviderAdapter;
use App\Services\Integrations\WebhookAdapter;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->tag([WebhookAdapter::class], IntegrationAdapter::class);
        $this->app->singleton(IntegrationRegistry::class, function ($app): IntegrationRegistry {
            $adapters = iterator_to_array($app->tagged(IntegrationAdapter::class));
            foreach (config('services.integrations.oauth', []) as $key => $configuration) {
                if (($configuration['enabled'] ?? false) === true) {
                    $adapters[] = new OAuthProviderAdapter($key, $configuration);
                }
            }

            return new IntegrationRegistry($adapters);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn (object $user, string $token): string => rtrim((string) config('app.url'), '/').'/reset-password/?'.http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(3)->by($email.'|'.$request->ip());
        });
    }
}
