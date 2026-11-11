<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Illuminate\Config\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;

final readonly class SlackWebhook
{
    public function __construct(
        private Factory $http,
        private Repository $config,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function send(string $url, array $payload): void
    {
        $this->http
            ->timeout(max(1, $this->config->integer('slack-errors.timeout')))
            ->asJson()
            ->post($url, $payload)
            ->throw();
    }
}
