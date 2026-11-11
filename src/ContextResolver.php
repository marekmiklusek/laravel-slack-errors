<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Throwable;
use Illuminate\Http\Request;
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

        $request = $this->app->make(Request::class);

        return ['URL' => $request->method().' '.$request->url()];
    }

    private function command(): ?string
    {
        $argv = $_SERVER['argv'] ?? null;

        return is_array($argv) ? implode(' ', array_filter($argv, is_string(...))) : null;
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
