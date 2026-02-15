<?php

declare(strict_types=1);

namespace Tivins\Solid\Cli;

use Tivins\Solid\ISP\EmptyMethodRuleChecker;
use Tivins\Solid\ISP\FatInterfaceRuleChecker;
use Tivins\Solid\ISP\InterfaceSegregationPrincipleChecker;
use Tivins\Solid\ISP\IspViolation;
use Tivins\Solid\LSP\LiskovSubstitutionPrincipleChecker;
use Tivins\Solid\LSP\LspViolation;
use Tivins\Solid\LSP\ParameterTypeContravarianceRuleChecker;
use Tivins\Solid\LSP\ReturnTypeCovarianceRuleChecker;
use Tivins\Solid\LSP\ThrowsContractRuleChecker;
use Tivins\Solid\LSP\ThrowsDetector;
use Tivins\Solid\LSP\TypeSubtypeChecker;
use Tivins\Solid\Process\ClassFinder;
use Tivins\Solid\Process\FormatType;
use Tivins\Solid\Process\StdWriter;
use ReflectionException;

/**
 * Application runner: loads classes from config, runs LSP/ISP checkers, returns structured result.
 * Can be tested without executing the CLI binary.
 */
final class Application
{
    public function run(CliOptions $options, StdWriter $writer): RunResult
    {
        $finder = new ClassFinder();
        $classes = $finder->findClassesFromConfig($options->config);
        if ($classes === []) {
            return new RunResult([], [], [], []);
        }

        $allViolations = [];
        $allErrors = [];
        $failedClassNames = [];

        if ($options->runLsp) {
            $typeChecker = new TypeSubtypeChecker();
            $lspChecker = new LiskovSubstitutionPrincipleChecker([
                new ThrowsContractRuleChecker(new ThrowsDetector()),
                new ReturnTypeCovarianceRuleChecker($typeChecker),
                new ParameterTypeContravarianceRuleChecker($typeChecker),
            ]);
            $writer->message("Checking Liskov Substitution Principle...", "\n\n");
            $this->runPrincipleLoop(
                $classes,
                $lspChecker->check(...),
                fn (LspViolation $v) => [
                    'principle' => 'LSP',
                    'className' => $v->className,
                    'methodName' => $v->methodName,
                    'contractName' => $v->contractName,
                    'reason' => $v->reason,
                    'details' => $v->details,
                ],
                $writer,
                $allViolations,
                $allErrors,
                $failedClassNames,
            );
        }

        if ($options->runIsp) {
            $ispChecker = new InterfaceSegregationPrincipleChecker([
                new EmptyMethodRuleChecker(),
                new FatInterfaceRuleChecker($options->ispThreshold),
            ]);
            $writer->message("\nChecking Interface Segregation Principle...", "\n\n");
            $this->runPrincipleLoop(
                $classes,
                $ispChecker->check(...),
                fn (IspViolation $v) => [
                    'principle' => 'ISP',
                    'className' => $v->className,
                    'interfaceName' => $v->interfaceName,
                    'reason' => $v->reason,
                    'details' => $v->details,
                ],
                $writer,
                $allViolations,
                $allErrors,
                $failedClassNames,
            );
        }

        $writer->message("");
        $writer->message("Classes checked: " . count($classes));
        $failedCount = count(array_unique($failedClassNames));
        $writer->message("Passed: " . (count($classes) - $failedCount) . " / " . count($classes));
        $writer->message("Total violations: " . count($allViolations));

        return new RunResult(
            classes: $classes,
            violations: $allViolations,
            errors: $allErrors,
            failedClassNames: $failedClassNames,
        );
    }

    /**
     * @param list<string> $classes
     * @param callable(string): array $check Returns list of violation objects
     * @param callable(mixed): array<string, mixed> $violationToArray
     * @param list<array<string, mixed>> $allViolations
     * @param list<array{class: string, message: string}> $allErrors
     * @param list<string> $failedClassNames
     */
    private function runPrincipleLoop(
        array $classes,
        callable $check,
        callable $violationToArray,
        StdWriter $writer,
        array &$allViolations,
        array &$allErrors,
        array &$failedClassNames,
    ): void {
        foreach ($classes as $class) {
            $loadError = null;
            try {
                $violations = $check($class);
            } catch (ReflectionException $e) {
                $violations = [];
                $loadError = $e->getMessage();
            }

            $ok = $loadError === null && $violations === [];
            $writer->content(($ok ? "[PASS]" : "[FAIL]") . " $class", FormatType::TEXT);

            if (!$ok) {
                $failedClassNames[] = $class;
                if ($loadError !== null) {
                    $allErrors[] = ['class' => $class, 'message' => $loadError];
                    $writer->content("       -> Error: $loadError", FormatType::TEXT);
                } else {
                    foreach ($violations as $violation) {
                        $allViolations[] = $violationToArray($violation);
                        $writer->content("       -> $violation", FormatType::TEXT);
                    }
                }
            }
        }
    }
}
