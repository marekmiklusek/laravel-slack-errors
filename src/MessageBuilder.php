<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

final readonly class MessageBuilder
{
    private const int MAX_FRAMES = 10;

    private const int MAX_FIELDS = 10;

    private const array ENTRY_POINTS = ['public/index.php', 'artisan'];

    public function __construct(
        private Application $app,
        private Repository $config,
        private ContextResolver $context,
    ) {}

    /**
     * @return array{text?: string, attachments: list<array{color?: string, fallback: string, blocks: list<array<string, mixed>>}>}
     */
    public function build(Throwable $exception): array
    {
        $class = $exception::class;
        $message = Str::limit($exception->getMessage() !== '' ? $exception->getMessage() : '(no message)', 500);
        $mention = $this->mention();

        $blocks = [
            [
                'type' => 'header',
                'text' => ['type' => 'plain_text', 'text' => Str::limit('🪲 '.$this->title(), 140), 'emoji' => true],
            ],
            $this->section(
                '*'.$this->escape($class).'*'
                ."\n\n".$this->escape($message)
                ."\n\n`".$this->escape($this->location($exception)).'`',
            ),
        ];

        $fields = $this->fields();

        if ($fields !== []) {
            $blocks[] = ['type' => 'section', 'fields' => $fields];
        }

        $trace = $this->trace($exception);

        if ($trace !== '') {
            $blocks[] = $this->section("```\n".$this->escape($trace)."\n```");
        }

        $fallback = $this->escape(Str::limit($this->title().': '.$class.': '.$message, 300));

        $attachment = ['fallback' => $fallback, 'blocks' => $blocks];

        $color = $this->color();

        if ($color !== null) {
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
            $fields[] = $this->field($label, $this->escape(Str::limit($value, 1900)));
        }

        return array_slice($fields, 0, self::MAX_FIELDS);
    }

    private function location(Throwable $exception): string
    {
        return $this->relativePath($exception->getFile()).':'.$exception->getLine();
    }

    private function trace(Throwable $exception): string
    {
        $frames = [];

        foreach ($exception->getTrace() as $frame) {
            $file = $frame['file'] ?? null;

            if ($file === null || ! $this->isApplicationFile($file)) {
                continue;
            }

            $frames[] = sprintf(
                '%s:%d %s%s%s()',
                $this->relativePath($file),
                $frame['line'] ?? 0,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'],
            );

            if (count($frames) === self::MAX_FRAMES) {
                break;
            }
        }

        return Str::limit(implode("\n", $frames), 2500);
    }

    /**
     * @return array{type: string, text: array{type: string, text: string}}
     */
    private function section(string $text): array
    {
        return ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $text]];
    }

    /**
     * @return array{type: string, text: string}
     */
    private function field(string $label, string $value): array
    {
        return ['type' => 'mrkdwn', 'text' => '*'.$label."*\n".$value];
    }

    private function title(): string
    {
        $name = $this->config->get('app.name');
        $environment = $this->config->get('app.env');

        $title = is_string($name) && $name !== '' ? $name : 'Laravel';

        return is_string($environment) && $environment !== '' ? $title.' | '.$environment : $title;
    }

    private function color(): ?string
    {
        $color = $this->config->get('slack-errors.color');

        return is_string($color) && $color !== '' ? $color : null;
    }

    private function mention(): string
    {
        $mention = $this->config->get('slack-errors.mention');

        return is_string($mention) ? mb_trim($mention) : '';
    }

    private function relativePath(string $file): string
    {
        $base = $this->basePath();
        $file = $this->normalize($file);

        return str_starts_with($file, $base) ? mb_substr($file, mb_strlen($base)) : $file;
    }

    private function isApplicationFile(string $file): bool
    {
        if (! str_starts_with($this->normalize($file), $this->basePath())) {
            return false;
        }

        $relative = $this->relativePath($file);

        return ! str_starts_with($relative, 'vendor/') && ! in_array($relative, self::ENTRY_POINTS, true);
    }

    private function basePath(): string
    {
        return mb_rtrim($this->normalize($this->app->basePath()), '/').'/';
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
