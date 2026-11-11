<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

use Throwable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

final readonly class Throttle
{
    public function __construct(
        private CacheFactory $cache,
        private Repository $config,
    ) {}

    public function attempt(Throwable $exception): bool
    {
        $minutes = $this->minutes();

        if ($minutes === 0) {
            return true;
        }

        try {
            return $this->cache->store($this->store())->add($this->key($exception), true, $minutes * 60);
        } catch (Throwable) {
            return true;
        }
    }

    private function key(Throwable $exception): string
    {
        return 'slack-errors:'.hash('xxh128', $exception::class.'|'.$exception->getFile().'|'.$exception->getLine());
    }

    private function minutes(): int
    {
        $minutes = $this->config->get('slack-errors.throttle');

        return is_int($minutes) && $minutes > 0 ? $minutes : 0;
    }

    private function store(): ?string
    {
        $store = $this->config->get('slack-errors.cache_store');

        return is_string($store) && $store !== '' ? $store : null;
    }
}
