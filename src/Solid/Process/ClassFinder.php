<?php

declare(strict_types=1);

namespace Tivins\Solid\Process;

use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tivins\Solid\LSP\Config;

/**
 * Scans a directory recursively for PHP files and extracts fully qualified class names.
 *
 * Uses nikic/php-parser to reliably detect class declarations (with full namespace resolution).
 * Each file containing at least one class is loaded via require_once so the classes
 * become available for reflection.
 */
class ClassFinder
{
    /**
     * Scan a directory recursively for PHP files and return all fully qualified class names found.
     *
     * If the target directory (or an ancestor up to 3 levels) contains a vendor/autoload.php,
     * it is included first so that dependencies are available.
     *
     * @return string[] Fully qualified class names (sorted alphabetically)
     * @throws InvalidArgumentException If the directory does not exist or is not readable
     */
    public function findClassesInDirectory(string $directory): array
    {
        $realDir = realpath($directory);
        if ($realDir === false || !is_dir($realDir)) {
            throw new InvalidArgumentException("Directory not found or not readable: $directory");
        }

        $this->includeAutoloaderIfPresent($realDir);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $classes = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realDir),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $code = file_get_contents($filePath);
            if ($code === false) {
                continue;
            }

            $stmts = $parser->parse($code);
            if ($stmts === null) {
                continue;
            }

            $fileClasses = $this->extractClassNames($stmts);

            if (!empty($fileClasses)) {
                require_once $filePath;
                array_push($classes, ...$fileClasses);
            }
        }

        sort($classes);
        return $classes;
    }

    /**
     * Collect PHP file paths from config (directories + explicit files, respecting exclusions),
     * then parse each file and return fully qualified class names.
     *
     * @return string[] Fully qualified class names (sorted alphabetically)
     */
    public function findClassesFromConfig(Config $config): array
    {
        $filePaths = $this->collectFilesFromConfig($config);
        if (empty($filePaths)) {
            return [];
        }

        $firstPath = reset($filePaths);
        $this->includeAutoloaderIfPresent(is_file($firstPath) ? dirname($firstPath) : $firstPath);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $classes = [];

        foreach ($filePaths as $filePath) {
            if (!is_file($filePath) || pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }
            $code = file_get_contents($filePath);
            if ($code === false) {
                continue;
            }
            $stmts = $parser->parse($code);
            if ($stmts === null) {
                continue;
            }
            $fileClasses = $this->extractClassNames($stmts);
            if (!empty($fileClasses)) {
                require_once $filePath;
                array_push($classes, ...$fileClasses);
            }
        }

        $classes = array_unique($classes);
        sort($classes);
        return array_values($classes);
    }

    /**
     * Build a list of PHP file paths from config: scan directories (with exclusions) and add explicit files.
     *
     * @return list<string> Absolute file paths
     */
    private function collectFilesFromConfig(Config $config): array
    {
        $excludedDirs = array_values(array_filter(array_map('realpath', $config->getExcludedDirectories())));
        $excludedFiles = array_values(array_filter(array_map('realpath', $config->getExcludedFiles())));
        $seen = [];
        $paths = [];

        foreach ($config->getDirectories() as $dir) {
            $realDir = realpath($dir);
            if ($realDir === false || !is_dir($realDir)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realDir));
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $path = $file->getRealPath();
                if ($path === false || isset($seen[$path])) {
                    continue;
                }
                if ($this->isPathUnderAny($path, $excludedDirs) || in_array($path, $excludedFiles, true)) {
                    continue;
                }
                $seen[$path] = true;
                $paths[] = $path;
            }
        }

        foreach ($config->getFiles() as $file) {
            $path = realpath($file);
            if ($path === false || !is_file($path) || isset($seen[$path])) {
                continue;
            }
            if (pathinfo($path, PATHINFO_EXTENSION) !== 'php' || in_array($path, $excludedFiles, true)) {
                continue;
            }
            $seen[$path] = true;
            $paths[] = $path;
        }

        return array_values($paths);
    }

    /**
     * @param string $path Absolute path
     * @param list<string> $dirs Absolute directory paths
     */
    private function isPathUnderAny(string $path, array $dirs): bool
    {
        foreach ($dirs as $dir) {
            if ($path === $dir || str_starts_with($path . DIRECTORY_SEPARATOR, $dir . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract fully qualified class names from a parsed AST.
     *
     * @param Node\Stmt[] $stmts
     * @return string[]
     */
    private function extractClassNames(array $stmts): array
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $collector = new class extends NodeVisitorAbstract {
            /** @var string[] */
            public array $classes = [];

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\Class_ && $node->namespacedName !== null) {
                    $this->classes[] = $node->namespacedName->toString();
                }
                return null;
            }
        };

        $traverser->addVisitor($collector);
        $traverser->traverse($stmts);

        return $collector->classes;
    }

    /**
     * Look for a vendor/autoload.php in the given directory or up to 3 parent levels,
     * and include it if found. This ensures dependencies of the scanned project are loaded.
     */
    private function includeAutoloaderIfPresent(string $directory): void
    {
        $dir = $directory;
        for ($i = 0; $i < 4; $i++) {
            $autoload = $dir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
                return;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break; // reached filesystem root
            }
            $dir = $parent;
        }
    }
}
