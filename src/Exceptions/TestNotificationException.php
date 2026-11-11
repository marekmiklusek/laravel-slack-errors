<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors\Exceptions;

use RuntimeException;

final class TestNotificationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Test notification from slack-errors:test. If you can read this, Slack errors are set up correctly.');
    }
}
