<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Throwable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

final class ErrorReporter
{
    private bool $reporting = false;

    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
        private readonly MessageBuilder $builder,
        private readonly SlackWebhook $webhook,
    ) {}

    public function report(Throwable $exception): void
    {
        $webhookUrl = $this->webhookUrl();

        if ($this->reporting || $webhookUrl === null || ! $this->shouldReport($exception)) {
            return;
        }

        $this->reporting = true;

        try {
            $this->webhook->send($webhookUrl, $this->builder->build($exception));
        } catch (Throwable) {
            //
        } finally {
            $this->reporting = false;
        }
    }

    private function shouldReport(Throwable $exception): bool
    {
        return $this->config->get('slack-errors.enabled') === true
            && $this->app->environment($this->environments()) === true
            && ! $this->isIgnored($exception);
    }

    private function webhookUrl(): ?string
    {
        $url = $this->config->get('slack-errors.webhook_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @return list<string>
     */
    private function environments(): array
    {
        $environments = $this->config->get('slack-errors.environments');

        return is_array($environments) ? array_values(array_filter($environments, is_string(...))) : [];
    }

    private function isIgnored(Throwable $exception): bool
    {
        $ignored = $this->config->get('slack-errors.ignore');

        return is_array($ignored)
            && array_any($ignored, fn (mixed $class): bool => is_string($class) && $exception instanceof $class);
    }
}
