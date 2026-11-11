<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\RequestGuard;
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
    return new ContextResolver($app, $job ?? new CurrentJob)->resolve();
}

/**
 * @return array<string, string>
 */
function resolveHttpContext(Application $app, ?Request $request = null): array
{
    $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';

    try {
        $app->instance(Request::class, $request ?? Request::create('https://shop.test/'));

        return resolveContext($app);
    } finally {
        unset($_SERVER['APP_RUNNING_IN_CONSOLE']);
    }
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
    $_SERVER['argv'] = ['artisan', 'user:create', 'marek@example.com', '--password=secret'];

    try {
        $context = resolveContext(contextApp());
    } finally {
        $_SERVER['argv'] = $argv;
    }

    expect($context)->toBe(['Command' => 'artisan user:create']);
});

it('names the queue job being processed', function (): void {
    $job = new CurrentJob;
    $job->push('App\Jobs\SyncOrders');

    expect(resolveContext(contextApp(), $job))->toBe(['Job' => 'App\Jobs\SyncOrders']);
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

it('leaves the path out when no route was resolved', function (): void {
    $request = Request::create('https://shop.test/reset-password/secret-token?step=2', 'POST');

    expect(resolveHttpContext(contextApp(), $request))->toBe(['URL' => 'POST https://shop.test (route not resolved)']);
});

it('shows the route template instead of the real path', function (): void {
    $request = Request::create('https://shop.test/reset-password/secret-token?email=marek@example.com', 'POST');
    $request->setRouteResolver(fn (): Route => new Route(['POST'], 'reset-password/{token}', fn (): null => null));

    expect(resolveHttpContext(contextApp(), $request))->toBe(['URL' => 'POST https://shop.test/reset-password/{token}']);
});

it('includes the user the application already loaded', function (): void {
    $guard = new RequestGuard(fn (): null => null, Request::create('/'));
    $guard->setUser(new GenericUser(['id' => 42]));

    $app = contextApp();
    $app->instance(AuthFactory::class, authWithGuard($guard));

    expect(resolveHttpContext($app))->toHaveKey('User', '42');
});

it('never reports a user in the console', function (): void {
    $guard = new RequestGuard(fn (): null => null, Request::create('/'));
    $guard->setUser(new GenericUser(['id' => 42]));

    $app = contextApp();
    $app->instance(AuthFactory::class, authWithGuard($guard));

    expect(resolveContext($app))->not->toHaveKey('User');
});

it('skips the user when none is loaded', function (): void {
    $app = contextApp();
    $app->instance(AuthFactory::class, authWithGuard(new RequestGuard(fn (): null => null, Request::create('/'))));

    expect(resolveHttpContext($app))->not->toHaveKey('User');
});

it('skips the user when the auth guard fails', function (): void {
    $app = contextApp();
    $app->bind(AuthFactory::class, fn (): never => throw new RuntimeException('Session store not set on request.'));

    expect(resolveHttpContext($app))->not->toHaveKey('User');
});
