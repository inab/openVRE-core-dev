<?php

namespace OpenVRE;

use MongoDB\BSON\Persistable;
use Monolog\Logger;


class Tool extends Persistable
{
    private array $arguments = [];
    private bool $isExternal;
    private string $id;
    private ToolInfrastructure $infrastructure;
    private array $inputFiles = [];
    private bool $isActive; // status
    private string $longDescription;
    private string $name;
    private array $outputFiles = [];
    private array $owner = [];
    private string $shortDescription;
    private string $title;
    private string $url;
    private Logger $logger;

    public function __construct(array $arguments, bool $isExternal, string $id, ToolInfrastructure $infrastructure, array $inputFiles, bool $isActive, string $longDescription, string $name, array $outputFiles, array $owner, string $shortDescription, string $title, string $url)
    {
        $this->logger = new Logger('Tool');

        $this->arguments = $arguments;
        $this->isExternal = $isExternal;
        $this->id = $id;
        $this->infrastructure = $infrastructure;
        $this->inputFiles = $inputFiles;
        $this->isActive = $isActive;
        $this->longDescription = $longDescription;
        $this->name = $name;
        $this->outputFiles = $outputFiles;
        $this->owner = $owner;
        $this->shortDescription = $shortDescription;
        $this->title = $title;
        $this->url = $url;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    public function getIsExternal(): bool
    {
        return $this->isExternal;
    }

    public function setIsExternal(bool $isExternal): void
    {
        $this->isExternal = $isExternal;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getInfrastructure(): ToolInfrastructure
    {
        return $this->infrastructure;
    }

    public function setInfrastructure(ToolInfrastructure $infrastructure): void
    {
        $this->infrastructure = $infrastructure;
    }

    public function getInputFiles(): array
    {
        return $this->inputFiles;
    }

    public function setInputFiles(array $inputFiles): void
    {
        $this->inputFiles = $inputFiles;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getLongDescription(): string
    {
        return $this->longDescription;
    }

    public function setLongDescription(string $longDescription): void
    {
        $this->longDescription = $longDescription;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getOutputFiles(): array
    {
        return $this->outputFiles;
    }

    public function setOutputFiles(array $outputFiles): void
    {
        $this->outputFiles = $outputFiles;
    }

    public function getOwner(): array
    {
        return $this->owner;
    }

    public function setOwner(array $owner): void
    {
        $this->owner = $owner;
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): void
    {
        $this->shortDescription = $shortDescription;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function bsonSerialize(): array
    {
        $data = get_object_vars($this);
        $data['_id'] = $this->getId();
        unset($data['id']);
        unset($data['logger']);
        return $data;
    }

    public function bsonUnserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            } elseif ($key == '_id') {
                $this->setId($value);
            }
        }
    }
}
