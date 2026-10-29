<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Gate;
use App\Models\{Athlete, Exercise, ProgramExercise, User, WorkoutProgram};
use App\Policies\{AthletePolicy, ExercisePolicy, ProgramExercisePolicy, UserPolicy, WorkoutProgramPolicy};


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
        Gate::policy(Exercise::class, ExercisePolicy::class);
        Gate::policy(WorkoutProgram::class, WorkoutProgramPolicy::class);
        Gate::policy(ProgramExercise::class, ProgramExercisePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Athlete::class, AthletePolicy::class);

        RateLimiter::for('auth', function (Request $request) {
            $identifier = Str::lower((string) ($request->input('identifier') ?? $request->input('email') ?? ''));

            return [
                // Stops one IP from hammering many different accounts.
                Limit::perMinute(10)->by('auth-ip:' . $request->ip()),
                // Stops many IPs from hammering a single account (distributed brute force).
                Limit::perMinute(5)->by('auth-identifier:' . $identifier),
            ];
        });

        // General per-user API limit (falls back to IP). Generous: Flutter issues few requests.
        RateLimiter::for(
            'api',
            fn(Request $request) =>
            Limit::perMinute(120)->by($request->user()?->id ?: $request->ip())
        );

        // Password changes: guessing the current password with a stolen token must be slow.
        RateLimiter::for(
            'sensitive',
            fn(Request $request) =>
            Limit::perMinute(5)->by('sensitive:' . ($request->user()?->id ?: $request->ip()))
        );

        // Admin write operations on users.
        RateLimiter::for(
            'admin-writes',
            fn(Request $request) =>
            Limit::perMinute(30)->by('admin-writes:' . ($request->user()?->id ?: $request->ip()))
        );

        // No frontend reset page exists yet. This builds a placeholder URL so
        // Password::sendResetLink() doesn't fail trying to resolve a
        // non-existent 'password.reset' web route. With MAIL_MAILER=log, the
        // token is readable directly in storage/logs/laravel.log for testing.
        // Replace with a real deep link once a reset UI exists.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return sprintf(
                '%s/reset-password?token=%s&email=%s',
                config('app.url'),
                $token,
                urlencode($notifiable->getEmailForPasswordReset()),
            );
        });
    }
}
