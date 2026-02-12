<?php

use Tivins\LSP\LiskovSubstitutionPrincipleChecker;
use Tivins\LSP\ThrowsDetector;
use Tivins\Process\FormatType;
use Tivins\Process\StdWriter;

require 'vendor/autoload.php';

// Todo later: load classes from a directory/namespace/etc or a configuration file.
require_once __dir__ . '/liskov-principles-violation-example.php';
$classes = [
    MyClass1::class,
    MyClass2::class,
    MyClass3::class,
    MyClass4::class,
    MyClass5::class,
];

$format = FormatType::TEXT;
if (in_array('--json', $argv)) {
    $format = FormatType::JSON;
}
$verbose = !in_array('--quiet', $argv);

$writer = new StdWriter($verbose, $format);
$checker = new LiskovSubstitutionPrincipleChecker(new ThrowsDetector());
$writer->message("Checking Liskov Substitution Principle...", "\n\n");

$totalViolations = 0;
$failedClasses = 0;
$allViolations = [];

foreach ($classes as $class) {

    try {
        $violations = $checker->check($class);
        $ok = count($violations) === 0;
    } catch (ReflectionException $e) {
        $ok = false;
        $violations[$class] = $e->getMessage();
    }

    $writer->content(($ok ? "[PASS]" : "[FAIL]") . " $class", FormatType::TEXT);

    if (!$ok) {
        $failedClasses++;
        $totalViolations += count($violations);
        $allViolations = array_merge($allViolations, $violations);
        foreach ($violations as $violation) {
            $writer->content("       -> $violation", FormatType::TEXT);
        }
    }
}

$writer->message("");
$writer->message("Classes checked: " . count($classes));
$writer->message("Passed: " . (count($classes) - $failedClasses) . " / " . count($classes));
$writer->message("Total violations: $totalViolations");

$writer->content(json_encode($allViolations, JSON_PRETTY_PRINT), FormatType::JSON);

// Exit with code 1 if there were any failures, 0 otherwise.
exit($failedClasses > 0 ? 1 : 0);