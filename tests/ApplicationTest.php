<?php

declare(strict_types=1);

namespace Tivins\Solid\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\Solid\Cli\Application;
use Tivins\Solid\Cli\CliOptions;
use Tivins\Solid\Config;
use Tivins\Solid\ISP\FatInterfaceRuleChecker;
use Tivins\Solid\Process\FormatType;
use Tivins\Solid\Process\StdWriter;

/**
 * Unit tests for Application (runner): run result structure without executing the binary.
 */
final class ApplicationTest extends TestCase
{
    private static string $projectRoot;

    private static string $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__);
        self::$fixturesPath = self::$projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures';
    }

    public function testRunWithNoClassesReturnsEmptyResult(): void
    {
        $config = (new Config())->addDirectory(self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-no-classes');
        $options = new CliOptions(
            config: $config,
            format: FormatType::JSON,
            verbose: false,
            runLsp: true,
            runIsp: true,
            ispThreshold: FatInterfaceRuleChecker::DEFAULT_THRESHOLD,
        );
        $writer = new StdWriter(false, FormatType::JSON);
        $result = (new Application())->run($options, $writer);
        $this->assertSame([], $result->classes);
        $this->assertSame([], $result->violations);
        $this->assertSame([], $result->errors);
        $this->assertSame(0, $result->getFailedCount());
    }

    public function testRunWithCompliantDirectoryReturnsZeroFailed(): void
    {
        $config = (new Config())->addDirectory(self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-only-ok');
        $options = new CliOptions(
            config: $config,
            format: FormatType::JSON,
            verbose: false,
            runLsp: true,
            runIsp: true,
            ispThreshold: FatInterfaceRuleChecker::DEFAULT_THRESHOLD,
        );
        $writer = new StdWriter(false, FormatType::JSON);
        $result = (new Application())->run($options, $writer);
        $this->assertNotEmpty($result->classes);
        $this->assertSame([], $result->violations);
        $this->assertSame(0, $result->getFailedCount());
        $report = $result->toJsonReport();
        $this->assertArrayHasKey('violations', $report);
        $this->assertArrayHasKey('errors', $report);
    }

    public function testRunWithLspViolationsReturnsStructuredViolations(): void
    {
        $config = (new Config())->addDirectory(self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-example');
        $options = new CliOptions(
            config: $config,
            format: FormatType::JSON,
            verbose: false,
            runLsp: true,
            runIsp: false,
            ispThreshold: FatInterfaceRuleChecker::DEFAULT_THRESHOLD,
        );
        $writer = new StdWriter(false, FormatType::JSON);
        $result = (new Application())->run($options, $writer);
        $this->assertGreaterThan(0, $result->getFailedCount());
        $this->assertGreaterThan(0, $result->getTotalViolations());
        foreach ($result->violations as $v) {
            $this->assertSame('LSP', $v['principle']);
            $this->assertArrayHasKey('className', $v);
            $this->assertArrayHasKey('methodName', $v);
            $this->assertArrayHasKey('contractName', $v);
            $this->assertArrayHasKey('reason', $v);
        }
    }

    public function testRunIspOnlySkipsLsp(): void
    {
        $config = (new Config())->addDirectory(self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-isp-violation');
        $options = new CliOptions(
            config: $config,
            format: FormatType::JSON,
            verbose: false,
            runLsp: false,
            runIsp: true,
            ispThreshold: FatInterfaceRuleChecker::DEFAULT_THRESHOLD,
        );
        $writer = new StdWriter(false, FormatType::JSON);
        $result = (new Application())->run($options, $writer);
        $this->assertGreaterThan(0, count($result->violations));
        foreach ($result->violations as $v) {
            $this->assertSame('ISP', $v['principle']);
            $this->assertArrayHasKey('interfaceName', $v);
        }
    }
}
