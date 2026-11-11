<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

final readonly class ContextResolver
{
    public function __construct(
        private Application $app,
        private CurrentJob $job,
    ) {}

    /**
     * @return array<string, string>
     */
    public function resolve(): array
    {
        return array_filter([
            ...$this->origin(),
            'User' => $this->userId(),
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function origin(): array
    {
        if ($this->app->runningInConsole()) {
            $job = $this->job->name();

            return $job !== null ? ['Job' => $job] : ['Command' => $this->command()];
        }

        return ['URL' => $this->url($this->app->make(Request::class))];
    }

    private function url(Request $request): string
    {
        $route = $request->route();
        $path = $route instanceof Route ? $route->uri() : $request->path();

        return $request->method().' '.$request->root().'/'.mb_ltrim($path, '/');
    }

    private function command(): ?string
    {
        $argv = $_SERVER['argv'] ?? null;

        if (! is_array($argv)) {
            return null;
        }

        $script = is_string($argv[0] ?? null) ? basename($argv[0]) : 'php';
        $command = array_find(
            array_slice($argv, 1),
            fn (mixed $argument): bool => is_string($argument) && ! str_starts_with($argument, '-'),
        );

        return is_string($command) ? $script.' '.$command : $script;
    }

    private function userId(): ?string
    {
        try {
            $guard = $this->app->make(AuthFactory::class)->guard();

            return $guard->hasUser() ? (string) $guard->id() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
