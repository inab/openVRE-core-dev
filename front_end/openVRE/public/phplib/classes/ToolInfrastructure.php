<?php

namespace OpenVRE;

use MongoDB\BSON\Persistable;
use Monolog\Logger;


class ToolInfrastructure extends Persistable
{
    private array $clouds = [];
    private int $cpus;
    private string $executable;
    private string $image;
    private bool $isInteractive;
    private int $memory;
    private int $port;
    private array $volumes = [];
    private Logger $logger;

    public function __construct(array $clouds, int $cpus, string $executable, string $image, bool $isInteractive, int $memory, int $port, array $volumes)
    {
        $this->logger = LoggerFactory::getLogger('Tool infrastructure');

        $this->clouds = $clouds;
        $this->cpus = $cpus;
        $this->executable = $executable;
        $this->image = $image;
        $this->isInteractive = $isInteractive;
        $this->memory = $memory;
        $this->port = $port;
        $this->volumes = $volumes;
    }

    public function getClouds(): array
    {
        return $this->clouds;
    }

    public function setClouds(array $clouds): void
    {
        $this->clouds = $clouds;
    }

    public function getCpus(): int
    {
        return $this->cpus;
    }

    public function setCpus(int $cpus): void
    {
        $this->cpus = $cpus;
    }

    public function getExecutable(): string
    {
        return $this->executable;
    }

    public function setExecutable(string $executable): void
    {
        $this->executable = $executable;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function setImage(string $image): void
    {
        $this->image = $image;
    }

    public function getIsInteractive(): bool
    {
        return $this->isInteractive;
    }

    public function setIsInteractive(bool $isInteractive): void
    {
        $this->isInteractive = $isInteractive;
    }

    public function getMemory(): int
    {
        return $this->memory;
    }

    public function setMemory(int $memory): void
    {
        $this->memory = $memory;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    public function getVolumes(): array
    {
        return $this->volumes;
    }

    public function setVolumes(array $volumes): void
    {
        $this->volumes = $volumes;
    }
}
