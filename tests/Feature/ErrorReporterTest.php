<?php

declare(strict_types=1);

use Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MarekMiklusek\SlackErrors\ErrorReporter;

function repeatedException(): RuntimeException
{
    return new RuntimeException('Repeated failure');
}

it('sends the exception to the configured webhook', function (): void {
    Http::fake();

    app(ErrorReporter::class)->report(new RuntimeException('Payment failed'));

    Http::assertSent(fn (Request $request): bool => $request->url() === TestCase::WEBHOOK_URL
        && $request->method() === 'POST'
        && str_contains($request->body(), 'Payment failed'));
});

it('does not send when disabled', function (): void {
    Http::fake();
    config(['slack-errors.enabled' => false]);

    app(ErrorReporter::class)->report(new RuntimeException('Payment failed'));

    Http::assertNothingSent();
});

it('does not send without a webhook url', function (?string $url): void {
    Http::fake();
    config(['slack-errors.webhook_url' => $url]);

    app(ErrorReporter::class)->report(new RuntimeException('Payment failed'));

    Http::assertNothingSent();
})->with(['missing' => [null], 'empty' => ['']]);

it('does not send outside the configured environments', function (): void {
    Http::fake();
    config(['slack-errors.environments' => ['production', 'staging']]);

    app(ErrorReporter::class)->report(new RuntimeException('Payment failed'));

    Http::assertNothingSent();
});

it('sends from every environment with a wildcard', function (): void {
    Http::fake();
    config(['slack-errors.environments' => ['*']]);

    app(ErrorReporter::class)->report(new RuntimeException('Payment failed'));

    Http::assertSentCount(1);
});

it('does not send ignored exceptions or their subclasses', function (): void {
    Http::fake();
    config(['slack-errors.ignore' => [RuntimeException::class]]);

    app(ErrorReporter::class)->report(new UnexpectedValueException('Ignored subclass'));

    Http::assertNothingSent();
});

it('swallows slack failures and keeps reporting afterwards', function (): void {
    Http::fake(['*' => Http::response('invalid_payload', 400)]);

    $reporter = app(ErrorReporter::class);

    expect(function () use ($reporter): void {
        $reporter->report(new RuntimeException('First'));
    })->not->toThrow(Throwable::class);

    $reporter->report(new RuntimeException('Second'));

    Http::assertSentCount(2);
});

it('does not report again while a report is in progress', function (): void {
    Http::fake(function (): mixed {
        app(ErrorReporter::class)->report(new RuntimeException('Nested'));

        return Http::response('ok');
    });

    app(ErrorReporter::class)->report(new RuntimeException('Outer'));

    Http::assertSentCount(1);
});

it('sends the same exception only once within the throttle window', function (): void {
    Http::fake();

    app(ErrorReporter::class)->report(repeatedException());
    app(ErrorReporter::class)->report(repeatedException());

    Http::assertSentCount(1);
});

it('sends the exception again when the previous attempt failed', function (): void {
    Http::fake(['*' => Http::response('invalid_payload', 400)]);

    app(ErrorReporter::class)->report(repeatedException());
    app(ErrorReporter::class)->report(repeatedException());

    Http::assertSentCount(2);
});
