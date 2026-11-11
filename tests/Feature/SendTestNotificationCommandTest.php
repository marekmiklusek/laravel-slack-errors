<?php

declare(strict_types=1);

use Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;

it('sends a test notification regardless of the environment filter', function (): void {
    Http::fake();
    config(['slack-errors.enabled' => false, 'slack-errors.environments' => ['production']]);

    expect(Artisan::call('slack-errors:test'))->toBe(0)
        ->and(Artisan::output())->toContain('Test notification sent');

    Http::assertSent(fn (Request $request): bool => $request->url() === TestCase::WEBHOOK_URL
        && str_contains($request->body(), 'TestNotificationException'));
});

it('fails without a webhook url', function (): void {
    Http::fake();
    config(['slack-errors.webhook_url' => null]);

    expect(Artisan::call('slack-errors:test'))->toBe(1)
        ->and(Artisan::output())->toContain('SLACK_ERRORS_WEBHOOK_URL');

    Http::assertNothingSent();
});

it('shows why slack rejected the notification', function (): void {
    Http::fake(['*' => Http::response('no_service', 404)]);

    expect(Artisan::call('slack-errors:test'))->toBe(1)
        ->and(Artisan::output())->toContain('Slack did not accept the notification');
});
