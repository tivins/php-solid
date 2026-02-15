<?php

declare(strict_types=1);

namespace Tivins\Solid\Cli;

use Tivins\Solid\Config;
use Tivins\Solid\Process\FormatType;

/**
 * DTO holding parsed CLI options for the php-solid application.
 */
readonly class CliOptions
{
    public function __construct(
        public Config $config,
        public FormatType $format,
        public bool $verbose,
        public bool $runLsp,
        public bool $runIsp,
        public int $ispThreshold,
    ) {
    }
}
