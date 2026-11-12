<?php

declare(strict_types=1);

use Tests\TestCase;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobProcessed;
use MarekMiklusek\SlackErrors\CurrentJob;
use Illuminate\Queue\Events\JobProcessing;
use MarekMiklusek\SlackErrors\ErrorReporter;
use Illuminate\Foundation\Exceptions\Handler;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Debug\ExceptionHandler;
use MarekMiklusek\SlackErrors\SlackErrorsServiceProvider;
use NunoMaduro\Collision\Adapters\Laravel\ExceptionHandler as CollisionHandler;

it('registers the package service provider', function (): void {
    expect(app()->getLoadedProviders())
        ->toHaveKey(SlackErrorsServiceProvider::class);
});

it('merges the package configuration', function (): void {
    expect(config()->boolean('slack-errors.enabled'))->toBeTrue()
        ->and(config()->integer('slack-errors.timeout'))->toBe(3)
        ->and(config()->string('slack-errors.mention'))->toBe('')
        ->and(config()->integer('slack-errors.throttle'))->toBe(10)
        ->and(config()->string('slack-errors.icon'))->toBe('🙈')
        ->and(config()->string('slack-errors.color'))->toBe('#ECB22E')
        ->and(config()->array('slack-errors.ignore'))->toBe([]);
});

it('publishes the configuration file', function (): void {
    expect(ServiceProvider::pathsToPublish(SlackErrorsServiceProvider::class, 'slack-errors-config'))
        ->toHaveCount(1);
});

it('registers the reporter as a singleton', function (): void {
    expect(app(ErrorReporter::class))->toBe(app(ErrorReporter::class));
});

it('sends exceptions reported through the framework handler to slack', function (): void {
    Http::fake();

    report(new RuntimeException('Boom from the handler'));

    Http::assertSent(fn (Request $request): bool => $request->url() === TestCase::WEBHOOK_URL
        && str_contains($request->body(), 'Boom from the handler'));
});

it('hooks into decorated exception handlers that forward reportable callbacks', function (): void {
    Http::fake();

    app()->singleton(ExceptionHandler::class, fn (): ExceptionHandler => new class implements ExceptionHandler
    {
        /** @var list<callable> */
        private array $callbacks = [];

        public function reportable(callable $reportUsing): void
        {
            $this->callbacks[] = $reportUsing;
        }

        public function report(Throwable $e): void
        {
            foreach ($this->callbacks as $callback) {
                $callback($e);
            }
        }

        public function shouldReport(Throwable $e): bool
        {
            return true;
        }

        public function render(mixed $request, Throwable $e): Response
        {
            return new Response;
        }

        public function renderForConsole(mixed $output, Throwable $e): void {}
    });

    app(ExceptionHandler::class)->report(new RuntimeException('Decorated handler'));

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'Decorated handler'));
});

it('does not hook into exception handlers without reportable callbacks', function (): void {
    Http::fake();

    app()->singleton(ExceptionHandler::class, fn (): ExceptionHandler => new class implements ExceptionHandler
    {
        public function report(Throwable $e): void {}

        public function shouldReport(Throwable $e): bool
        {
            return true;
        }

        public function render(mixed $request, Throwable $e): Response
        {
            return new Response;
        }

        public function renderForConsole(mixed $output, Throwable $e): void {}
    });

    app(ExceptionHandler::class)->report(new RuntimeException('Custom handler'));

    Http::assertNothingSent();
});

it('tracks the queue job being processed', function (): void {
    $job = new SyncJob(app(), json_encode([
        'displayName' => 'App\Jobs\SyncOrders',
        'job' => 'Illuminate\Queue\CallQueuedHandler@call',
        'data' => [],
    ], JSON_THROW_ON_ERROR), 'sync', 'default');

    event(new JobProcessing('sync', $job));

    expect(app(CurrentJob::class)->name())->toBe('App\Jobs\SyncOrders');

    event(new JobProcessed('sync', $job));

    expect(app(CurrentJob::class)->name())->toBeNull();
});

it('forgets a failed job before the worker polls again', function (): void {
    app(CurrentJob::class)->push('App\Jobs\SyncOrders');

    event(new Looping('database', 'default'));

    expect(app(CurrentJob::class)->name())->toBeNull();
});

it('keeps the outer job after a nested synchronous job', function (): void {
    $job = fn (string $name): SyncJob => new SyncJob(app(), json_encode([
        'displayName' => $name,
        'job' => 'Illuminate\Queue\CallQueuedHandler@call',
        'data' => [],
    ], JSON_THROW_ON_ERROR), 'sync', 'default');

    event(new JobProcessing('database', $job('App\Jobs\ImportOrders')));
    event(new JobProcessing('sync', $job('App\Jobs\SendInvoice')));
    event(new JobProcessed('sync', $job('App\Jobs\SendInvoice')));

    expect(app(CurrentJob::class)->name())->toBe('App\Jobs\ImportOrders');
});

it('hooks the Collision handler only once', function (): void {
    Http::fake();
    config(['slack-errors.throttle' => 0]);

    $original = new Handler(app());
    app()->singleton(ExceptionHandler::class, fn (): CollisionHandler => new CollisionHandler(app(), $original));

    $provider = app()->getProvider(SlackErrorsServiceProvider::class);

    assert($provider instanceof SlackErrorsServiceProvider);

    $provider->boot();

    app(ExceptionHandler::class)->report(new RuntimeException('Through Collision'));

    Http::assertSentCount(1);
});

it('lets Laravel log the exception after sending it', function (): void {
    Http::fake();

    /** @var list<string> $logged */
    $logged = [];

    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message;
    });

    report(new RuntimeException('Still logged'));

    Http::assertSentCount(1);

    expect($logged)->toContain('Still logged');
});
