<?php

declare(strict_types=1);

namespace Tivins\Solid\Process;

class StdWriter
{
    public function __construct(
        public bool $verbose = true,
        public FormatType $format = FormatType::TEXT,
    ) {
    }

    public function message(string $message, string $suffix = "\n"): void
    {
        if (!$this->verbose) {
            return;
        }
        fwrite(STDERR, $message . $suffix);
    }

    public function content(string $message, FormatType $format = FormatType::TEXT, string $suffix = "\n"): void
    {
        if ($this->format !== $format) {
            return;
        }
        fwrite(STDOUT, $message . $suffix);
    }
}