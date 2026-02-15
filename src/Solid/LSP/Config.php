<?php

declare(strict_types=1);

namespace Tivins\Solid\LSP;

class Config
{
    /** @var list<string> */
    private array $files = [];
    /** @var list<string> */
    private array $directories = [];
    /** @var list<string> */
    private array $excludedDirectories = [];
    /** @var list<string> */
    private array $excludedFiles = [];


    public function addFile(string $path): self
    {
        $this->files[] = $path;
        return $this;
    }

    public function addDirectory(string $path): self
    {
        $this->directories[] = $path;
        return $this;
    }
    
    public function excludeDirectory(string $path): self
    {
        $this->excludedDirectories[] = $path;
        return $this;
    }

    public function excludeFile(string $path): self
    {
        $this->excludedFiles[] = $path;
        return $this;
    }

    /** @return list<string> */
    public function getFiles(): array
    {
        return $this->files;
    }

    /** @return list<string> */
    public function getExcludedFiles(): array
    {
        return $this->excludedFiles;
    }

    /** @return list<string> */
    public function getDirectories(): array
    {
        return $this->directories;
    }

    /** @return list<string> */
    public function getExcludedDirectories(): array
    {
        return $this->excludedDirectories;
    }
}