<?php

declare(strict_types=1);

use MarekMiklusek\SlackErrors\MessageBuilder;

function messageException(string $message = 'Order <42> & co'): RuntimeException
{
    return new RuntimeException($message);
}

function nestedMessageException(int $depth): RuntimeException
{
    return $depth === 0 ? new RuntimeException('Deep') : nestedMessageException($depth - 1);
}

/**
 * @return array{text?: string, attachments: list<array{color: string, fallback: string, blocks: list<array<string, mixed>>}>}
 */
function buildMessage(Throwable $exception): array
{
    return app(MessageBuilder::class)->build($exception);
}

it('builds the fallback text and header', function (): void {
    config(['app.name' => 'Shop']);

    $payload = buildMessage(messageException());

    expect($payload)->not->toHaveKey('text')
        ->and($payload['attachments'][0]['fallback'])->toBe('Shop | testing: RuntimeException: Order &lt;42&gt; &amp; co')
        ->and($payload['attachments'][0]['blocks'][0])->toBe([
            'type' => 'header',
            'text' => ['type' => 'plain_text', 'text' => '🪲 Shop | testing', 'emoji' => true],
        ]);
});

it('falls back to a default app name', function (): void {
    config(['app.name' => null]);

    expect(data_get(buildMessage(messageException()), 'attachments.0.blocks.0.text.text'))->toBe('🪲 Laravel | testing');
});

it('leaves the environment out of the header when it is unknown', function (): void {
    config(['app.name' => 'Shop', 'app.env' => null]);

    expect(data_get(buildMessage(messageException()), 'attachments.0.blocks.0.text.text'))->toBe('🪲 Shop');
});

it('describes the exception with escaped mrkdwn', function (): void {
    expect(data_get(buildMessage(messageException()), 'attachments.0.blocks.1.text.text'))
        ->toStartWith("*RuntimeException*\n\nOrder &lt;42&gt; &amp; co\n\n`");
});

it('prepends the configured mention', function (): void {
    config(['slack-errors.mention' => '<!channel>']);

    $payload = buildMessage(messageException());

    expect(data_get($payload, 'text'))->toBe('<!channel> Laravel | testing: RuntimeException: Order &lt;42&gt; &amp; co')
        ->and(data_get($payload, 'attachments.0.blocks.1.text.text'))->toStartWith('*RuntimeException*');
});

it('handles exceptions without a message', function (): void {
    expect(data_get(buildMessage(messageException('')), 'attachments.0.blocks.1.text.text'))
        ->toStartWith("*RuntimeException*\n\n(no message)\n\n`");
});

it('truncates long messages', function (): void {
    $text = data_get(buildMessage(messageException(str_repeat('a', 2000))), 'attachments.0.blocks.1.text.text');

    assert(is_string($text));

    expect(mb_strlen(explode("\n", $text)[2]))->toBeLessThanOrEqual(503);
});

it('shows the location on its own line under the exception', function (): void {
    $text = data_get(buildMessage(messageException()), 'attachments.0.blocks.1.text.text');

    assert(is_string($text));

    expect(explode("\n", $text)[4])->toStartWith('`')
        ->toContain('MessageBuilderTest.php:')
        ->toEndWith('`');
});

it('lists the context as fields', function (): void {
    expect(data_get(buildMessage(messageException()), 'attachments.0.blocks.2.fields.0.text'))->toStartWith("*Command*\n");
});

it('shows paths relative to the application base path', function (): void {
    app()->setBasePath(dirname(__DIR__, 2));

    expect(data_get(buildMessage(messageException()), 'attachments.0.blocks.1.text.text'))
        ->toContain("\n`tests/Feature/MessageBuilderTest.php:");
});

it('adds at most ten application frames of the stack trace', function (): void {
    app()->setBasePath(dirname(__DIR__, 2));

    $trace = data_get(buildMessage(nestedMessageException(15)), 'attachments.0.blocks.3.text.text');

    assert(is_string($trace));

    expect($trace)
        ->toStartWith("```\n")
        ->toContain('nestedMessageException()')
        ->and(explode("\n", $trace))->toHaveCount(12);
});

it('omits the stack trace without frames from the project', function (): void {
    expect(buildMessage(new RuntimeException('Vendor only'))['attachments'][0]['blocks'])->toHaveCount(3);
});

it('wraps the blocks in a colored attachment', function (): void {
    $payload = buildMessage(messageException());

    expect($payload)->not->toHaveKey('blocks')
        ->and($payload['attachments'])->toHaveCount(1)
        ->and($payload['attachments'][0]['color'])->toBe('#ECB22E');
});
