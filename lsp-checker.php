<?php

use Tivins\LSP\LiskovSubstitutionPrincipleChecker;

require 'vendor/autoload.php';

// Todo later: load classes from a directory/namespace/etc or a configuration file.
require_once __dir__ . '/liskov-principles-violation-example.php';
$classes = [
    MyClass1::class,
    MyClass2::class,
    MyClass3::class,
];


$checker = new LiskovSubstitutionPrincipleChecker();
echo "Checking Liskov Substitution Principle...\n\n";

$totalViolations = 0;
$failedClasses = 0;

foreach ($classes as $class) {
    $violations = $checker->check($class);
    $ok = count($violations) === 0;

    echo ($ok ? "[PASS]" : "[FAIL]") . " $class\n";

    if (!$ok) {
        $failedClasses++;
        $totalViolations += count($violations);
        foreach ($violations as $violation) {
            echo "       -> $violation\n";
        }
    }
}

echo "\n";
echo "Classes checked: " . count($classes) . "\n";
echo "Passed: " . (count($classes) - $failedClasses) . " / " . count($classes) . "\n";
echo "Total violations: $totalViolations\n";

fwrite(STDERR, json_encode($violations, JSON_PRETTY_PRINT));

// Exit with code 1 if there were any failures, 0 otherwise.
$exitCode = $failedClasses > 0 ? 1 : 0;
exit($exitCode);