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

    public function __construct(
        private Application $app,
        private Repository $config,
        private ContextResolver $context,
    ) {}

    /**
     * @return array{text: string, blocks: list<array<string, mixed>>}
     */
    public function build(Throwable $exception): array
    {
        $class = $exception::class;
        $message = Str::limit($exception->getMessage() !== '' ? $exception->getMessage() : '(no message)', 500);
        $mention = $this->mention();

        $blocks = [
            [
                'type' => 'header',
                'text' => ['type' => 'plain_text', 'text' => Str::limit('🚨 '.$this->appName(), 140), 'emoji' => true],
            ],
            $this->section(mb_ltrim($mention.' *'.$this->escape($class).'*')."\n".$this->escape($message)),
            ['type' => 'section', 'fields' => $this->fields($exception)],
        ];

        $trace = $this->trace($exception);

        if ($trace !== '') {
            $blocks[] = $this->section("```\n".$this->escape($trace)."\n```");
        }

        return [
            'text' => mb_ltrim($mention.' '.$this->escape(Str::limit($this->appName().': '.$class.': '.$message, 300))),
            'blocks' => $blocks,
        ];
    }

    /**
     * @return list<array{type: string, text: string}>
     */
    private function fields(Throwable $exception): array
    {
        $location = $this->relativePath($exception->getFile()).':'.$exception->getLine();

        $fields = [$this->field('Location', '`'.$this->escape($location).'`')];

        foreach ($this->context->resolve() as $label => $value) {
            $fields[] = $this->field($label, $this->escape(Str::limit($value, 1900)));
        }

        return array_slice($fields, 0, self::MAX_FIELDS);
    }

    private function trace(Throwable $exception): string
    {
        $frames = [];

        foreach ($exception->getTrace() as $frame) {
            $file = $frame['file'] ?? null;

            if ($file === null || $this->isVendor($file)) {
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

    private function appName(): string
    {
        $name = $this->config->get('app.name');

        return is_string($name) && $name !== '' ? $name : 'Laravel';
    }

    private function mention(): string
    {
        $mention = $this->config->get('slack-errors.mention');

        return is_string($mention) ? mb_trim($mention) : '';
    }

    private function relativePath(string $file): string
    {
        $base = mb_rtrim($this->normalize($this->app->basePath()), '/').'/';
        $file = $this->normalize($file);

        return str_starts_with($file, $base) ? mb_substr($file, mb_strlen($base)) : $file;
    }

    private function isVendor(string $file): bool
    {
        return str_contains($this->normalize($file), '/vendor/');
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
