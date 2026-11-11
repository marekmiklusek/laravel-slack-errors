<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as Orchestra;
use MarekMiklusek\SlackErrors\SlackErrorsServiceProvider;

abstract class TestCase extends Orchestra
{
    public const string WEBHOOK_URL = 'https://hooks.slack.test/services/fake-webhook';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SlackErrorsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app->make(Repository::class)->set([
            'slack-errors.webhook_url' => self::WEBHOOK_URL,
            'slack-errors.environments' => ['testing'],
            'logging.default' => 'null',
            'cache.default' => 'array',
        ]);
    }
}
