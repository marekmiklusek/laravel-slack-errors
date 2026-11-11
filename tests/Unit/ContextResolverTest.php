<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\RequestGuard;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Application;
use MarekMiklusek\SlackErrors\CurrentJob;
use MarekMiklusek\SlackErrors\ContextResolver;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

function contextApp(): Application
{
    return new Application(dirname(__DIR__, 2));
}

/**
 * @return array<string, string>
 */
function resolveContext(Application $app, ?CurrentJob $job = null): array
{
    return new ContextResolver($app, new Repository(['app' => ['env' => 'production']]), $job ?? new CurrentJob)->resolve();
}

function authWithGuard(Guard $guard): AuthFactory
{
    return new readonly class($guard) implements AuthFactory
    {
        public function __construct(private Guard $guard) {}

        public function guard(mixed $name = null): Guard
        {
            return $this->guard;
        }

        public function shouldUse(mixed $name): void {}
    };
}

it('describes a console command', function (): void {
    $argv = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = ['artisan', 'orders:sync', '--force'];

    try {
        $context = resolveContext(contextApp());
    } finally {
        $_SERVER['argv'] = $argv;
    }

    expect($context)
        ->toMatchArray(['Environment' => 'production', 'Command' => 'artisan orders:sync --force'])
        ->toHaveKey('Host')
        ->not->toHaveKeys(['URL', 'IP', 'User', 'Job']);
});

it('names the queue job being processed', function (): void {
    $job = new CurrentJob;
    $job->set('App\Jobs\SyncOrders');

    expect(resolveContext(contextApp(), $job))->toHaveKey('Job', 'App\Jobs\SyncOrders');
});

it('skips the command when argv is unavailable', function (): void {
    $argv = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = null;

    try {
        $context = resolveContext(contextApp());
    } finally {
        $_SERVER['argv'] = $argv;
    }

    expect($context)->not->toHaveKey('Command');
});

it('describes an http request', function (): void {
    $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';

    try {
        $app = contextApp();
        $app->instance(Request::class, Request::create(
            'https://shop.test/checkout?step=2',
            'POST',
            server: ['REMOTE_ADDR' => '203.0.113.7'],
        ));

        $context = resolveContext($app);
    } finally {
        unset($_SERVER['APP_RUNNING_IN_CONSOLE']);
    }

    expect($context)
        ->toMatchArray(['URL' => 'POST https://shop.test/checkout?step=2', 'IP' => '203.0.113.7'])
        ->not->toHaveKey('Command');
});

it('includes the user the application already loaded', function (): void {
    $guard = new RequestGuard(fn (): null => null, Request::create('/'));
    $guard->setUser(new GenericUser(['id' => 42]));

    $app = contextApp();
    $app->instance(AuthFactory::class, authWithGuard($guard));

    expect(resolveContext($app))->toHaveKey('User', '42');
});

it('skips the user when none is loaded', function (): void {
    $app = contextApp();
    $app->instance(AuthFactory::class, authWithGuard(new RequestGuard(fn (): null => null, Request::create('/'))));

    expect(resolveContext($app))->not->toHaveKey('User');
});

it('skips the user when the auth guard fails', function (): void {
    $app = contextApp();
    $app->bind(AuthFactory::class, fn (): never => throw new RuntimeException('Session store not set on request.'));

    expect(resolveContext($app))->not->toHaveKey('User');
});
