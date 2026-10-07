<?php

namespace App\Providers;

use App\Models\Order;
use App\Policies\AdminPolicy;
use App\Policies\OrderPolicy;
use Filament\Forms\Components\FileUpload;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        // This first-party storefront uses sessions only; it does not issue API tokens.
        Sanctum::getAccessTokenFromRequestUsing(static fn () => null);

        // Existing upload paths must originate from the edited record, never arbitrary client state.
        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->preventFilePathTampering(), isImportant: true);

        foreach (['Product', 'ProductVariant', 'ProductImage', 'Category', 'Banner', 'Supplier', 'SupplierOrder', 'Coupon', 'User', 'AuditLog'] as $model) {
            Gate::policy('App\\Models\\'.$model, AdminPolicy::class);
        }
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::define('access-admin', fn ($user) => $user->isAdmin());

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-account:'.$this->accountThrottleKey($request)),
        ]);
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by('register:'.$request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by('reset-ip:'.$request->ip()),
            Limit::perHour(5)->by('reset-account:'.$this->accountThrottleKey($request)),
        ]);
        RateLimiter::for('catalog', fn (Request $request) => Limit::perMinute(120)->by('catalog:'.$request->ip()));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(20)->by('checkout:'.$request->ip()));
        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(5)->by('orders:'.$request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by('uploads:'.$request->user()?->id.':'.$request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by('api:'.$request->ip()));

        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('app.frontend_url', config('app.url')), '/')
            .'/account/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]));
    }

    private function accountThrottleKey(Request $request): string
    {
        $email = $request->input('email');

        return hash('sha256', is_string($email) ? mb_strtolower(trim($email)) : 'invalid-input');
    }
}
