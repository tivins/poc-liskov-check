<?php

declare(strict_types=1);

/**
 * Example configuration file for php-solid.
 * Copy to e.g. php-solid-config.php and adapt paths.
 * Usage: php-solid --config php-solid-config.php
 */

use Tivins\Solid\LSP\Config;

return (new Config())
    ->addDirectory('path/to/folder')
    ->excludeDirectory('path/to/folder/excluded')
    ->addFile('path/to/file')
    ->excludeFile('path/to/excluded/file');
