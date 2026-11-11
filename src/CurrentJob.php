<?php

declare(strict_types=1);

namespace MarekMiklusek\SlackErrors;

final class CurrentJob
{
    /**
     * @var list<string>
     */
    private array $names = [];

    public function push(string $name): void
    {
        $this->names[] = $name;
    }

    public function pop(): void
    {
        array_pop($this->names);
    }

    public function clear(): void
    {
        $this->names = [];
    }

    public function name(): ?string
    {
        return $this->names[count($this->names) - 1] ?? null;
    }
}
