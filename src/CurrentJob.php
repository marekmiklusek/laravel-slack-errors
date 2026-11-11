<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

final class CurrentJob
{
    private ?string $name = null;

    public function set(?string $name): void
    {
        $this->name = $name;
    }

    public function name(): ?string
    {
        return $this->name;
    }
}
