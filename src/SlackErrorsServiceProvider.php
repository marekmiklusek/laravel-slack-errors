<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use WeakMap;
use Throwable;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Contracts\Debug\ExceptionHandler;
use MarekMiklusek\SlackErrors\Console\SendTestNotificationCommand;

final class SlackErrorsServiceProvider extends ServiceProvider
{
    /**
     * @var WeakMap<ExceptionHandler, true>|null
     */
    private ?WeakMap $hooked = null;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/slack-errors.php', 'slack-errors');

        $this->app->singleton(ErrorReporter::class);
        $this->app->singleton(CurrentJob::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/slack-errors.php' => $this->app->configPath('slack-errors.php'),
        ], 'slack-errors-config');

        if ($this->app->runningInConsole()) {
            $this->commands([SendTestNotificationCommand::class]);
        }

        $this->trackQueueJobs();

        $this->hooked ??= new WeakMap;

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if (! $this->hooked instanceof WeakMap || isset($this->hooked[$handler]) || ! method_exists($handler, 'reportable')) {
                return;
            }

            $this->hooked[$handler] = true;

            $handler->reportable(function (Throwable $exception): void {
                $this->app->make(ErrorReporter::class)->report($exception);
            });
        });
    }

    private function trackQueueJobs(): void
    {
        $events = $this->app->make(Dispatcher::class);

        $events->listen(JobProcessing::class, function (JobProcessing $event): void {
            $this->app->make(CurrentJob::class)->push($event->job->resolveName());
        });

        $events->listen(JobProcessed::class, function (): void {
            $this->app->make(CurrentJob::class)->pop();
        });

        $events->listen([Looping::class, WorkerStopping::class], function (): void {
            $this->app->make(CurrentJob::class)->clear();
        });
    }
}
