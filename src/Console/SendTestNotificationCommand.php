<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors\Console;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Config\Repository;
use MarekMiklusek\SlackErrors\SlackWebhook;
use Illuminate\Console\Attributes\Signature;
use MarekMiklusek\SlackErrors\MessageBuilder;
use Illuminate\Console\Attributes\Description;
use MarekMiklusek\SlackErrors\Exceptions\TestNotificationException;

#[Signature('slack-errors:test')]
#[Description('Send a test notification to the configured Slack webhook')]
final class SendTestNotificationCommand extends Command
{
    public function handle(Repository $config, MessageBuilder $builder, SlackWebhook $webhook): int
    {
        $url = $config->string('slack-errors.webhook_url');

        if ($url === '') {
            $this->components->error('Set SLACK_ERRORS_WEBHOOK_URL before sending a test notification.');

            return self::FAILURE;
        }

        try {
            $webhook->send($url, $builder->build(new TestNotificationException));
        } catch (Throwable $exception) {
            $this->components->error('Slack did not accept the notification: '.str_replace($url, '***', $exception->getMessage()));

            return self::FAILURE;
        }

        $this->components->info('Test notification sent. Check your Slack channel.');

        return self::SUCCESS;
    }
}
