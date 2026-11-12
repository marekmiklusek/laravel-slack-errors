<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Throwable;
use ReflectionClass;
use Illuminate\Contracts\Foundation\Application;

final readonly class StackTrace
{
    private const array ENTRY_POINTS = ['public/index.php', 'artisan'];

    private const string SERIALIZED_CLOSURE = 'laravel-serializable-closure://';

    public function __construct(
        private Application $app,
    ) {}

    /**
     * @return list<array{file: string, line: int, call: string}>
     */
    public function applicationFrames(Throwable $exception): array
    {
        $frames = [];

        foreach ($exception->getTrace() as $frame) {
            $file = $frame['file'] ?? null;

            if ($file === null || ! $this->isApplicationFile($file)) {
                continue;
            }

            $frames[] = [
                'file' => $this->relativePath($file),
                'line' => $frame['line'] ?? 0,
                'call' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'()',
            ];
        }

        return $frames;
    }

    public function origin(Throwable $exception): string
    {
        $file = $exception->getFile();

        $builtByOwnClass = $this->normalize($file) === $this->normalize((string) new ReflectionClass($exception)->getFileName());

        if (! $this->isApplicationFile($file) || $builtByOwnClass) {
            $frame = $this->applicationFrames($exception)[0] ?? null;

            if ($frame !== null) {
                return $frame['file'].':'.$frame['line'];
            }
        }

        return $this->relativePath($file).':'.$exception->getLine();
    }

    public function location(Throwable $exception): string
    {
        if (str_starts_with($exception->getFile(), self::SERIALIZED_CLOSURE)) {
            return '(queued closure)';
        }

        return $this->relativePath($exception->getFile()).':'.$exception->getLine();
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
}
