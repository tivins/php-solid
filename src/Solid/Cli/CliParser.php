<?php

declare(strict_types=1);

namespace Tivins\Solid\Cli;

use Tivins\Solid\Config;
use Tivins\Solid\ISP\FatInterfaceRuleChecker;
use Tivins\Solid\Process\FormatType;

/**
 * Parses argv and returns a CliOptions DTO.
 * Throws CliParseException on invalid or missing arguments.
 */
final class CliParser
{
    /**
     * @param list<string> $argv Typically $argv from the script
     * @throws CliParseException
     */
    public function parse(array $argv): CliOptions
    {
        $args = $argv;
        $config = $this->parseConfig($args);
        $format = in_array('--json', $args, true) ? FormatType::JSON : FormatType::TEXT;
        $verbose = !in_array('--quiet', $args, true);

        $runLsp = in_array('--lsp', $args, true);
        $runIsp = in_array('--isp', $args, true);
        if (!$runLsp && !$runIsp) {
            $runLsp = true;
            $runIsp = true;
        }

        $ispThresholdCli = $this->parseIspThreshold($args);
        $directory = $this->parseDirectory($args);

        if ($config === null) {
            if ($directory === null) {
                throw new CliParseException($this->usage());
            }
            if (!is_dir($directory)) {
                throw new CliParseException("Error: '$directory' is not a valid directory.");
            }
            $config = (new Config())->addDirectory($directory);
        }

        $ispThreshold = $ispThresholdCli ?? $config->getIspThreshold() ?? FatInterfaceRuleChecker::DEFAULT_THRESHOLD;

        return new CliOptions(
            config: $config,
            format: $format,
            verbose: $verbose,
            runLsp: $runLsp,
            runIsp: $runIsp,
            ispThreshold: $ispThreshold,
        );
    }

    /**
     * Extracts and loads config from --config <file>. Removes consumed args from $args.
     * @param array<int, string> $args
     * @throws CliParseException
     */
    private function parseConfig(array &$args): ?Config
    {
        $idx = array_search('--config', $args, true);
        if ($idx === false) {
            return null;
        }
        $configFilename = $args[$idx + 1] ?? null;
        if ($configFilename === null || $configFilename === '') {
            throw new CliParseException("Error: --config requires a file path.");
        }
        unset($args[$idx], $args[$idx + 1]);
        $args = array_values($args);

        if (!is_file($configFilename) || !is_readable($configFilename)) {
            throw new CliParseException("Config file '$configFilename' not found or not readable.");
        }
        $loaded = require $configFilename;
        if (!$loaded instanceof Config) {
            $got = is_object($loaded) ? get_class($loaded) : gettype($loaded);
            throw new CliParseException("Config file '$configFilename' must return a " . Config::class . " object. Got $got.");
        }
        return $loaded;
    }

    /**
     * @param array<int, string> $args
     * @throws CliParseException
     */
    private function parseIspThreshold(array $args): ?int
    {
        $idx = array_search('--isp-threshold', $args, true);
        if ($idx === false) {
            return null;
        }
        $value = $args[$idx + 1] ?? null;
        if ($value === null || !ctype_digit($value) || (int) $value < 1) {
            throw new CliParseException("Error: --isp-threshold requires a positive integer.");
        }
        return (int) $value;
    }

    /**
     * First non-option argument (used when no --config). Skips --config and --isp-threshold values.
     * @param array<int, string> $args
     */
    private function parseDirectory(array $args): ?string
    {
        $skipNext = false;
        foreach (array_slice($args, 1) as $arg) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }
            if ($arg === '--config' || $arg === '--isp-threshold') {
                $skipNext = true;
                continue;
            }
            if (!str_starts_with($arg, '--')) {
                return $arg;
            }
        }
        return null;
    }

    private function usage(): string
    {
        return "Usage: php-solid <directory> [options]\n"
            . "       php-solid --config <file> [options]\n"
            . "  <directory>          Path to a directory containing PHP files to check.\n"
            . "  --config <file>      Path to a PHP file that returns a " . Config::class . " instance.\n"
            . "  --lsp                Run only LSP checks.\n"
            . "  --isp                Run only ISP checks.\n"
            . "  --isp-threshold <n>  Fat interface method threshold (default: " . FatInterfaceRuleChecker::DEFAULT_THRESHOLD . ").\n"
            . "  --json               Output violations as JSON.\n"
            . "  --quiet              Minimal output.\n"
            . "\nRun unit tests with: vendor/bin/phpunit";
    }
}
