<?php

namespace App\Providers;

use App\Contracts\VideoProviderInterface;
use App\Enums\VideoErrorCode;
use App\Providers\Gemini\GeminiOmniProvider;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VideoProviderInterface::class, fn (): GeminiOmniProvider => new GeminiOmniProvider(
            apiKey: (string) config('services.gemini.key'),
            model: config('services.gemini.model'),
            baseUrl: config('services.gemini.base_url'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Limit video generation per IP to keep API cost under control.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('videos', fn (Request $request): array => collect([
            Limit::perMinute(3)->by('videos-minute:'.$request->ip()),
            Limit::perDay(20)->by('videos-day:'.$request->ip()),
        ])->map(fn (Limit $limit): Limit => $limit->response(fn () => back()->withErrors([
            'prompt' => VideoErrorCode::RateLimited->message(),
        ])))->all());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
