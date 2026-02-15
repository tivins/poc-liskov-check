<?php

declare(strict_types=1);

namespace Tivins\Solid\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\Solid\Process\ClassFinder;

/**
 * Unit tests for ClassFinder: invalid directory, empty dir, files with/without classes,
 * implicit exclusions (no class = not listed), autoload inclusion.
 */
final class ClassFinderTest extends TestCase
{
    private static string $projectRoot;

    private static string $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__);
        self::$fixturesPath = self::$projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'classfinder';
    }

    public function testInvalidDirectoryThrowsInvalidArgumentException(): void
    {
        $finder = new ClassFinder();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory not found or not readable');
        $finder->findClassesInDirectory(self::$projectRoot . DIRECTORY_SEPARATOR . 'nonexistent-' . uniqid());
    }

    public function testEmptyDirectoryReturnsEmptyArray(): void
    {
        $finder = new ClassFinder();
        $dir = self::$fixturesPath . DIRECTORY_SEPARATOR . 'empty_dir';
        $classes = $finder->findClassesInDirectory($dir);
        $this->assertSame([], $classes);
    }

    public function testDirectoryWithOneClassReturnsSortedFqcn(): void
    {
        $finder = new ClassFinder();
        $dir = self::$fixturesPath . DIRECTORY_SEPARATOR . 'one_class';
        $classes = $finder->findClassesInDirectory($dir);
        $this->assertCount(1, $classes);
        $this->assertSame('Fixture\ClassFinder\SingleClass', $classes[0]);
    }

    /**
     * PHP files that do not declare any class are not listed (implicit exclusion).
     */
    public function testDirectoryWithPhpFileButNoClassReturnsEmpty(): void
    {
        $finder = new ClassFinder();
        $dir = self::$fixturesPath . DIRECTORY_SEPARATOR . 'no_class';
        $classes = $finder->findClassesInDirectory($dir);
        $this->assertSame([], $classes);
    }

    public function testResultIsSortedAlphabetically(): void
    {
        $finder = new ClassFinder();
        $dir = self::$projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cli-example';
        $classes = $finder->findClassesInDirectory($dir);
        $sorted = $classes;
        sort($sorted);
        $this->assertSame($sorted, $classes, 'findClassesInDirectory should return classes sorted alphabetically');
    }

    /**
     * When scanning a directory whose ancestor has vendor/autoload.php, the autoloader is included.
     * Scanning a dir under the project (e.g. fixtures) succeeds and returns classes.
     */
    public function testScanningDirectoryWithVendorAncestorSucceeds(): void
    {
        $finder = new ClassFinder();
        $dir = self::$projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cli-example';
        $classes = $finder->findClassesInDirectory($dir);
        $this->assertIsArray($classes);
        $this->assertNotEmpty($classes);
        $this->assertContains('FixtureClassViolation', $classes);
    }
}
