<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Throwable;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;

final class SlackErrorsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/slack-errors.php', 'slack-errors');

        $this->app->singleton(ErrorReporter::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/slack-errors.php' => $this->app->configPath('slack-errors.php'),
        ], 'slack-errors-config');

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if (! $handler instanceof Handler) {
                return;
            }

            $handler->reportable(function (Throwable $exception): void {
                $this->app->make(ErrorReporter::class)->report($exception);
            });
        });
    }
}
