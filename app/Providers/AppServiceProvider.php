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
use App\Services\SandboxGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
    }
}
