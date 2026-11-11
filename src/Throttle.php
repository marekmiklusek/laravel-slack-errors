<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Throwable;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class Throttle
{
    public function __construct(
        private CacheFactory $cache,
        private Repository $config,
        private StackTrace $stackTrace,
    ) {}

    public function attempt(Throwable $exception): bool
    {
        try {
            $minutes = $this->config->integer('slack-errors.throttle');

            if ($minutes <= 0) {
                return true;
            }

            return $this->store()->add($this->key($exception), true, $minutes * 60);
        } catch (Throwable) {
            return true;
        }
    }

    public function release(Throwable $exception): void
    {
        try {
            $this->store()->forget($this->key($exception));
        } catch (Throwable) {
            //
        }
    }

    private function key(Throwable $exception): string
    {
        return 'slack-errors:'.hash('xxh128', $exception::class.'|'.$this->stackTrace->origin($exception));
    }

    private function store(): CacheRepository
    {
        $store = $this->config->string('slack-errors.cache_store');

        return $this->cache->store($store !== '' ? $store : null);
    }
}
