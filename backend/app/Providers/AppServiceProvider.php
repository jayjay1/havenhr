<?php

namespace App\Providers;

use App\Contracts\OpenAIServiceInterface;
use App\Services\MockOpenAIService;
use App\Services\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext();
        });

        // Bind OpenAI service interface to the appropriate implementation.
        // Uses MockOpenAIService by default for development and testing.
        // Set OPENAI_API_KEY in .env and swap to OpenAIService for production.
        $this->app->bind(OpenAIServiceInterface::class, function () {
            $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));

            // Use real OpenAI service if API key is configured and package is installed
            if (! empty($apiKey) && class_exists(\OpenAI::class) && ! app()->environment('testing')) {
                return new \App\Services\OpenAIService();
            }

            // Fall back to mock for development and testing
            $delay = app()->environment('testing') ? 0 : 1;

            return new MockOpenAIService($delay);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     *
     * - 'api': 60 requests/min per authenticated user (general API endpoints)
     * - 'auth': 5 requests/min per IP for auth endpoints (login, register, password reset)
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $key = $user ? $user->id : $request->ip();

            return Limit::perMinute(60)->by($key)->response(function (Request $request, array $headers) {
                return response()->json([
                    'error' => [
                        'code' => 'RATE_LIMIT_EXCEEDED',
                        'message' => 'Too many requests. Please try again later.',
                    ],
                ], 429, $headers);
            });
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())->response(function (Request $request, array $headers) {
                // Log rate limit exceeded on auth endpoints for audit purposes
                Log::warning('Rate limit exceeded on auth endpoint', [
                    'ip_address' => $request->ip(),
                    'endpoint' => $request->path(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now()->toIso8601String(),
                ]);

                return response()->json([
                    'error' => [
                        'code' => 'RATE_LIMIT_EXCEEDED',
                        'message' => 'Too many requests. Please try again later.',
                    ],
                ], 429, $headers);
            });
        });
    }
}
