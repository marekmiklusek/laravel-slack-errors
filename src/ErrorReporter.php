<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Throwable;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

final class ErrorReporter
{
    private bool $reporting = false;

    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
        private readonly MessageBuilder $builder,
        private readonly SlackWebhook $webhook,
        private readonly Throttle $throttle,
    ) {}

    public function report(Throwable $exception): void
    {
        if ($this->reporting) {
            return;
        }

        $this->reporting = true;

        try {
            $this->send($exception);
        } catch (Throwable) {
            //
        } finally {
            $this->reporting = false;
        }
    }

    private function send(Throwable $exception): void
    {
        $webhookUrl = $this->config->string('slack-errors.webhook_url');

        if ($webhookUrl === '' || ! $this->shouldReport($exception) || ! $this->throttle->attempt($exception)) {
            return;
        }

        try {
            $this->webhook->send($webhookUrl, $this->builder->build($exception));
        } catch (Throwable $sendFailure) {
            $this->throttle->release($exception);

            throw $sendFailure;
        }
    }

    private function shouldReport(Throwable $exception): bool
    {
        return $this->config->boolean('slack-errors.enabled')
            && $this->app->environment($this->config->array('slack-errors.environments')) === true
            && ! $this->isIgnored($exception);
    }

    private function isIgnored(Throwable $exception): bool
    {
        return array_any(
            $this->config->array('slack-errors.ignore'),
            fn (mixed $class): bool => is_string($class) && $exception instanceof $class,
        );
    }
}
