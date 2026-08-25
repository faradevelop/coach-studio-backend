<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Gate;
use App\Models\{Exercise, ProgramExercise, User, WorkoutProgram};
use App\Policies\{ExercisePolicy, ProgramExercisePolicy, UserPolicy, WorkoutProgramPolicy};


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

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            $identifier = Str::lower((string) ($request->input('identifier') ?? $request->input('email') ?? ''));

            return [
                // Stops one IP from hammering many different accounts.
                Limit::perMinute(10)->by('auth-ip:' . $request->ip()),
                // Stops many IPs from hammering a single account (distributed brute force).
                Limit::perMinute(5)->by('auth-identifier:' . $identifier),
            ];
        });

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
