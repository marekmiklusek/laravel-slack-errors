<?php

declare(strict_types=1);

use Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MarekMiklusek\SlackErrors\SlackWebhook;
use Illuminate\Http\Client\RequestException;

it('posts the payload as json', function (): void {
    Http::fake();

    app(SlackWebhook::class)->send(TestCase::WEBHOOK_URL, ['text' => 'Hello']);

    Http::assertSent(fn (Request $request): bool => $request->url() === TestCase::WEBHOOK_URL
        && $request->isJson()
        && $request->data() === ['text' => 'Hello']);
});

it('throws when slack rejects the payload', function (): void {
    Http::fake(['*' => Http::response('invalid_payload', 400)]);

    expect(function (): void {
        app(SlackWebhook::class)->send(TestCase::WEBHOOK_URL, ['text' => 'Hello']);
    })->toThrow(RequestException::class);
});
