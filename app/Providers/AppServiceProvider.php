<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Notifications\WhatsAppNotificationService::class,
            \App\Services\Notifications\WhatsappService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce relative paths for all Vite assets to prevent port/origin mismatch and CORS issues
        Vite::createAssetPathsUsing(fn ($path) => '/' . ltrim($path, '/'));

        // Dynamically apply custom SMTP settings if configured in database
        try {
            $this->app->make(\App\Services\Notifications\MailNotificationService::class)->configureMailer();
        } catch (\Throwable $e) {
            // Ignore during early migrations / CLI setup
        }
    }
}
