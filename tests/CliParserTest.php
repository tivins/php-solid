<?php

declare(strict_types=1);

namespace Tivins\Solid\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\Solid\Cli\CliParseException;
use Tivins\Solid\Cli\CliParser;
use Tivins\Solid\Process\FormatType;

/**
 * Unit tests for CliParser: options parsing and CliOptions DTO.
 */
final class CliParserTest extends TestCase
{
    private static string $projectRoot;

    private static string $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__);
        self::$fixturesPath = self::$projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures';
    }

    public function testNoArgumentsThrowsWithUsage(): void
    {
        $parser = new CliParser();
        $this->expectException(CliParseException::class);
        $this->expectExceptionMessage('Usage:');
        $parser->parse(['php-solid']);
    }

    public function testInvalidDirectoryThrows(): void
    {
        $parser = new CliParser();
        $this->expectException(CliParseException::class);
        $this->expectExceptionMessage('not a valid directory');
        $parser->parse(['php-solid', '/nonexistent-' . uniqid()]);
    }

    public function testValidDirectoryReturnsOptionsWithBothPrinciples(): void
    {
        $dir = self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-only-ok';
        $parser = new CliParser();
        $options = $parser->parse(['php-solid', $dir]);
        $this->assertTrue($options->runLsp);
        $this->assertTrue($options->runIsp);
        $this->assertTrue($options->verbose);
        $this->assertSame(FormatType::TEXT, $options->format);
        $this->assertSame($dir, $options->config->getDirectories()[0] ?? null);
    }

    public function testLspOnlyDisablesIsp(): void
    {
        $dir = self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-only-ok';
        $parser = new CliParser();
        $options = $parser->parse(['php-solid', $dir, '--lsp']);
        $this->assertTrue($options->runLsp);
        $this->assertFalse($options->runIsp);
    }

    public function testIspOnlyDisablesLsp(): void
    {
        $dir = self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-only-ok';
        $parser = new CliParser();
        $options = $parser->parse(['php-solid', $dir, '--isp']);
        $this->assertFalse($options->runLsp);
        $this->assertTrue($options->runIsp);
    }

    public function testJsonFlagSetsFormat(): void
    {
        $dir = self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-only-ok';
        $parser = new CliParser();
        $options = $parser->parse(['php-solid', $dir, '--json']);
        $this->assertSame(FormatType::JSON, $options->format);
    }

    public function testQuietFlagSetsVerboseFalse(): void
    {
        $dir = self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-only-ok';
        $parser = new CliParser();
        $options = $parser->parse(['php-solid', $dir, '--quiet']);
        $this->assertFalse($options->verbose);
    }

    public function testIspThresholdParsed(): void
    {
        $dir = self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-only-ok';
        $parser = new CliParser();
        $options = $parser->parse(['php-solid', $dir, '--isp-threshold', '7']);
        $this->assertSame(7, $options->ispThreshold);
    }

    public function testIspThresholdInvalidThrows(): void
    {
        $dir = self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-only-ok';
        $parser = new CliParser();
        $this->expectException(CliParseException::class);
        $this->expectExceptionMessage('positive integer');
        $parser->parse(['php-solid', $dir, '--isp-threshold', 'abc']);
    }

    public function testConfigFileLoadsConfig(): void
    {
        $configFile = self::$fixturesPath . DIRECTORY_SEPARATOR . 'cli-config-returns-config.php';
        $parser = new CliParser();
        $options = $parser->parse(['php-solid', '--config', $configFile]);
        $this->assertCount(1, $options->config->getDirectories());
        $this->assertStringContainsString('cli-only-ok', $options->config->getDirectories()[0]);
    }

    public function testConfigFileMissingThrows(): void
    {
        $parser = new CliParser();
        $this->expectException(CliParseException::class);
        $this->expectExceptionMessage('not found or not readable');
        $parser->parse(['php-solid', '--config', '/nonexistent-config-' . uniqid() . '.php']);
    }
}
