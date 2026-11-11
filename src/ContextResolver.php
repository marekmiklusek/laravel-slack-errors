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
        if ($this->app->runningInConsole()) {
            $job = $this->job->name();

            return array_filter($job !== null ? ['Job' => $job] : ['Command' => $this->command()]);
        }

        return array_filter([
            'URL' => $this->url($this->app->make(Request::class)),
            'User' => $this->userId(),
        ]);
    }

    private function url(Request $request): string
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return $request->method().' '.$request->root().' (route not resolved)';
        }

        return $request->method().' '.$request->root().'/'.mb_ltrim($route->uri(), '/');
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
