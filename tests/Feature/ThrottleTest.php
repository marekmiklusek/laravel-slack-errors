<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use MarekMiklusek\SlackErrors\Throttle;

function throttledException(string $message = 'Order 1 not found'): RuntimeException
{
    return new RuntimeException($message);
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
