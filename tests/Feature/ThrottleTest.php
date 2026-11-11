<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use MarekMiklusek\SlackErrors\Throttle;

function throttledException(string $message = 'Order 1 not found'): RuntimeException
{
    return new RuntimeException($message);
}

function vendorException(): RuntimeException
{
    $exception = new RuntimeException('SQLSTATE[23000]: Integrity constraint violation');

    new ReflectionProperty(Exception::class, 'file')->setValue($exception, dirname(__DIR__, 2).'/vendor/laravel/framework/src/Illuminate/Database/Connection.php');
    new ReflectionProperty(Exception::class, 'line')->setValue($exception, 825);

    return $exception;
}

it('lets the first occurrence through and holds back repeats from the same place', function (): void {
    $throttle = app(Throttle::class);

    expect($throttle->attempt(throttledException('Order 1 not found')))->toBeTrue()
        ->and($throttle->attempt(throttledException('Order 2 not found')))->toBeFalse();
});

it('treats exceptions from different places separately', function (): void {
    $throttle = app(Throttle::class);

    $first = new RuntimeException('First place');
    $second = new RuntimeException('Second place');

    expect($throttle->attempt($first))->toBeTrue()
        ->and($throttle->attempt($second))->toBeTrue();
});

it('can be disabled', function (): void {
    config(['slack-errors.throttle' => 0]);

    $throttle = app(Throttle::class);

    expect($throttle->attempt(throttledException()))->toBeTrue()
        ->and($throttle->attempt(throttledException()))->toBeTrue();
});

it('uses the configured cache store', function (): void {
    config([
        'cache.stores.slack-errors' => ['driver' => 'array'],
        'slack-errors.cache_store' => 'slack-errors',
    ]);

    $throttle = app(Throttle::class);

    expect($throttle->attempt(throttledException()))->toBeTrue();

    Cache::store('slack-errors')->flush();

    expect($throttle->attempt(throttledException()))->toBeTrue();
});

it('lets everything through when the cache is unavailable', function (): void {
    config(['slack-errors.cache_store' => 'missing-store']);

    $throttle = app(Throttle::class);

    expect($throttle->attempt(throttledException()))->toBeTrue()
        ->and($throttle->attempt(throttledException()))->toBeTrue();
});

it('tells apart vendor exceptions coming from different places in the project', function (): void {
    app()->setBasePath(dirname(__DIR__, 2));

    $throttle = app(Throttle::class);

    $fromOrders = vendorException();
    $fromInvoices = vendorException();

    expect($throttle->attempt($fromOrders))->toBeTrue()
        ->and($throttle->attempt($fromInvoices))->toBeTrue();
});

it('holds back a vendor exception coming from the same place in the project', function (): void {
    app()->setBasePath(dirname(__DIR__, 2));

    $throttle = app(Throttle::class);

    $exceptions = [vendorException(), vendorException()];

    expect($throttle->attempt($exceptions[0]))->toBeTrue()
        ->and($throttle->attempt($exceptions[1]))->toBeFalse();
});

it('frees the window again after a release', function (): void {
    $throttle = app(Throttle::class);

    expect($throttle->attempt(throttledException()))->toBeTrue();

    $throttle->release(throttledException());

    expect($throttle->attempt(throttledException()))->toBeTrue();
});

it('ignores cache failures on release', function (): void {
    config(['slack-errors.cache_store' => 'missing-store']);

    expect(function (): void {
        app(Throttle::class)->release(throttledException());
    })->not->toThrow(Throwable::class);
});
