<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Config\Repository;

final readonly class MessageBuilder
{
    private const int MAX_FRAMES = 10;

    private const int MAX_FIELDS = 10;

    public function __construct(
        private Repository $config,
        private ContextResolver $context,
        private StackTrace $stackTrace,
    ) {}

    /**
     * @return array{text?: string, attachments: list<array{color?: string, fallback: string, blocks: list<array<string, mixed>>}>}
     */
    public function build(Throwable $exception): array
    {
        $class = $this->escape($exception::class);
        $message = $exception->getMessage() !== '' ? $this->escape($exception->getMessage()) : '(no message)';
        $mention = $this->config->string('slack-errors.mention');

        $blocks = [
            [
                'type' => 'header',
                'text' => ['type' => 'plain_text', 'text' => Str::limit('🪲 '.mb_scrub($this->title(), 'UTF-8'), 140), 'emoji' => true],
            ],
            $this->section(
                '*'.$class.'*'
                ."\n\n".Str::limit($message, 500)
                ."\n\n`".$this->escape($this->stackTrace->location($exception)).'`',
            ),
        ];

        $fields = $this->fields();

        if ($fields !== []) {
            $blocks[] = ['type' => 'section', 'fields' => $fields];
        }

        $trace = $this->trace($exception);

        if ($trace !== '') {
            $blocks[] = $this->section("```\n".$trace."\n```");
        }

        $fallback = Str::limit($this->escape($this->title()).': '.$class.': '.$message, 300);

        $attachment = ['fallback' => $fallback, 'blocks' => $blocks];

        $color = $this->config->string('slack-errors.color');

        if ($color !== '') {
            $attachment = ['color' => $color, ...$attachment];
        }

        $payload = ['attachments' => [$attachment]];

        if ($mention !== '') {
            $payload['text'] = $mention.' '.$fallback;
        }

        return $payload;
    }

    /**
     * @return list<array{type: string, text: string}>
     */
    private function fields(): array
    {
        $fields = [];

        foreach ($this->context->resolve() as $label => $value) {
            $fields[] = ['type' => 'mrkdwn', 'text' => '*'.$label."*\n".Str::limit($this->escape($value), 1900)];
        }

        return array_slice($fields, 0, self::MAX_FIELDS);
    }

    private function trace(Throwable $exception): string
    {
        $frames = array_map(
            fn (array $frame): string => $frame['file'].':'.$frame['line'].' '.$frame['call'],
            array_slice($this->stackTrace->applicationFrames($exception), 0, self::MAX_FRAMES),
        );

        return Str::limit($this->escape(implode("\n", $frames)), 2500);
    }

    /**
     * @return array{type: string, text: array{type: string, text: string}}
     */
    private function section(string $text): array
    {
        return ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $text]];
    }

    private function title(): string
    {
        $name = $this->config->string('app.name');
        $environment = $this->config->string('app.env');

        $title = $name !== '' ? $name : 'Laravel';

        return $environment !== '' ? $title.' | '.$environment : $title;
    }

    private function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], mb_scrub($value, 'UTF-8'));
    }
}
