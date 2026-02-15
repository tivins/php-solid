<?php

declare(strict_types=1);

namespace Tivins\Solid\Cli;

/**
 * Structured result of an Application run.
 */
readonly class RunResult
{
    /**
     * @param list<string> $classes All class names that were checked
     * @param list<array<string, mixed>> $violations All violations (for JSON: principle, className, ...)
     * @param list<array{class: string, message: string}> $errors Load/reflection errors per class
     * @param list<string> $failedClassNames Class names that had at least one violation or error
     */
    public function __construct(
        public array $classes,
        public array $violations,
        public array $errors,
        public array $failedClassNames,
    ) {
    }

    public function getFailedCount(): int
    {
        return count(array_unique($this->failedClassNames));
    }

    public function getTotalViolations(): int
    {
        return count($this->violations);
    }

    /** @return array{violations: list<array<string, mixed>>, errors: list<array{class: string, message: string}>} */
    public function toJsonReport(): array
    {
        return [
            'violations' => $this->violations,
            'errors' => $this->errors,
        ];
    }
}
