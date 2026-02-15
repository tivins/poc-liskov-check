<?php

declare(strict_types=1);

namespace Tivins\LSP\Tests;

use PHPUnit\Framework\TestCase;

/**
 * CLI integration tests: exit code, stdout/stderr, JSON structure.
 *
 * Runs the php-solid script and asserts behaviour to detect regressions.
 */
final class CliIntegrationTest extends TestCase
{
    private static string $projectRoot;

    private static string $phpBinary;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__);
        self::$phpBinary = PHP_BINARY ?? 'php';
    }

    private function runCli(array $args): array
    {
        $cmd = [self::$phpBinary, self::$projectRoot . DIRECTORY_SEPARATOR . 'php-solid', ...$args];
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open(
            $cmd,
            $spec,
            $pipes,
            self::$projectRoot,
            null,
        );
        self::assertNotFalse($proc, 'proc_open failed');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        return [
            'exit' => $exitCode,
            'stdout' => $stdout !== false ? $stdout : '',
            'stderr' => $stderr !== false ? $stderr : '',
        ];
    }

    public function testNoDirectoryPrintsUsageAndExitsWithCode2(): void
    {
        $result = $this->runCli([]);
        $this->assertSame(2, $result['exit'], 'Expected exit code 2 when no directory given');
        $this->assertStringContainsString('Usage:', $result['stderr']);
        $this->assertStringContainsString('php-solid <directory>', $result['stderr']);
        $this->assertStringContainsString('--json', $result['stderr']);
        $this->assertStringContainsString('--quiet', $result['stderr']);
    }

    public function testInvalidDirectoryExitsWithCode2(): void
    {
        $result = $this->runCli(['/nonexistent-path-' . uniqid()]);
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('not a valid directory', $result['stderr']);
    }

    public function testDirectoryWithNoPhpClassesExitsWithCode0(): void
    {
        $dir = self::$projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cli-no-classes';
        $result = $this->runCli([$dir]);
        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('No PHP classes found', $result['stderr']);
    }

    public function testDirectoryWithViolationsExitsWithCode1(): void
    {
        $dir = self::$projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cli-example';
        $result = $this->runCli([$dir]);
        $this->assertSame(1, $result['exit'], 'Expected exit code 1 when there are violations');
        $this->assertStringContainsString('[FAIL]', $result['stdout']);
    }

    public function testJsonOutputContainsViolationsAndErrorsKeys(): void
    {
        $dir = self::$projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cli-example';
        $result = $this->runCli([$dir, '--json']);
        $this->assertSame(1, $result['exit']);

        $json = json_decode($result['stdout'], true);
        $this->assertIsArray($json, 'stdout should be valid JSON');
        $this->assertArrayHasKey('violations', $json);
        $this->assertArrayHasKey('errors', $json);
        $this->assertIsArray($json['violations']);
        $this->assertIsArray($json['errors']);
    }

    public function testJsonViolationStructure(): void
    {
        $dir = self::$projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cli-example';
        $result = $this->runCli([$dir, '--json']);
        $json = json_decode($result['stdout'], true);
        $this->assertNotEmpty($json['violations'], 'Fixture has at least one violation');

        foreach ($json['violations'] as $v) {
            $this->assertIsArray($v);
            $this->assertArrayHasKey('className', $v);
            $this->assertArrayHasKey('methodName', $v);
            $this->assertArrayHasKey('contractName', $v);
            $this->assertArrayHasKey('reason', $v);
        }
    }

    public function testQuietModeReducesStderrOutput(): void
    {
        $dir = self::$projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cli-example';
        $verbose = $this->runCli([$dir]);
        $quiet = $this->runCli([$dir, '--quiet']);
        $this->assertGreaterThan(
            strlen($quiet['stderr']),
            strlen($verbose['stderr']),
            'Verbose mode should produce more stderr than --quiet',
        );
    }

    public function testDirectoryWithNoViolationsExitsWithCode0(): void
    {
        $dirOnlyOk = self::$projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cli-only-ok';
        $result = $this->runCli([$dirOnlyOk, '--json']);
        $this->assertSame(0, $result['exit']);
        $json = json_decode($result['stdout'], true);
        $this->assertEmpty($json['violations']);
        $this->assertEmpty($json['errors']);
    }
}
