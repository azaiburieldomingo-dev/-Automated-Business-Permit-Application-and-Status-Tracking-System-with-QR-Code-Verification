<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\SmsChannel;
use App\Events\ApplicationApprovedForPayment;
use App\Events\ApplicationRejected;
use App\Events\ApplicationReturnedForCorrection;
use App\Events\ApplicationSubmitted;
use App\Events\ApplicationUnderReview;
use App\Events\PaymentConfirmed;
use App\Events\PermitReleased;
use App\Listeners\SendStatusNotification;
use App\Services\LogSmsChannel;
use App\Services\ManualGateway;
use App\Services\SandboxGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Domain events mapped to the synchronous status-notification listener.
     *
     * @var array<class-string>
     */
    private const STATUS_EVENTS = [
        ApplicationSubmitted::class,
        ApplicationUnderReview::class,
        ApplicationReturnedForCorrection::class,
        ApplicationApprovedForPayment::class,
        PaymentConfirmed::class,
        PermitReleased::class,
        ApplicationRejected::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function ($app) {
            return match (config('permit.payment.mode')) {
                'manual' => $app->make(ManualGateway::class),
                'sandbox' => $app->make(SandboxGateway::class),
                default => $app->make(SandboxGateway::class),
            };
        });

        $this->app->bind(SmsChannel::class, function ($app) {
            return match (config('permit.sms.driver')) {
                'log' => $app->make(LogSmsChannel::class),
                default => $app->make(LogSmsChannel::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (self::STATUS_EVENTS as $event) {
            Event::listen($event, SendStatusNotification::class);
        }

        $this->configureRateLimiting();
    }

    /**
     * Separate buckets per endpoint. Plain `throttle:N,1` shares one per-IP
     * counter across every throttled guest route, so e.g. scanning a few QR
     * codes would lock the same device out of logging in.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by('login|'.Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('track', fn (Request $request) => Limit::perMinute(6)
            ->by('track|'.$request->ip()));

        RateLimiter::for('verify', fn (Request $request) => Limit::perMinute(30)
            ->by('verify|'.$request->ip()));

        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(30)
            ->by('webhook|'.$request->ip()));
    }
}
