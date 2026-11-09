<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Illuminate\Http\Client\Factory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;

final readonly class SlackWebhook
{
    private const int DEFAULT_TIMEOUT = 3;

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
            ->timeout($this->timeout())
            ->asJson()
            ->post($url, $payload)
            ->throw();
    }

    private function timeout(): int
    {
        $timeout = $this->config->get('slack-errors.timeout');

        return is_int($timeout) && $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
    }
}
