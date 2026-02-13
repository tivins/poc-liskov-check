<?php

declare(strict_types=1);

namespace Tivins\LSP\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\LSP\LiskovSubstitutionPrincipleChecker;
use Tivins\LSP\LspViolation;
use Tivins\LSP\ThrowsDetector;

/**
 * Unit tests for LiskovSubstitutionPrincipleChecker using the built-in example classes.
 *
 * Example classes are defined in liskov-principles-violation-example.php:
 * - MyClass1: interface has no @throws, implementation throws → violation
 * - MyClass2: interface has @throws RuntimeException, implementation throws → no violation
 * - MyClass3: interface has no @throws, implementation (via private) throws → violation
 * - MyClass4: interface has no @throws, code throws (no @throws docblock) → AST violation
 * - MyClass5: interface has no @throws, code throws via private method → AST violation
 * - MyClass2b: interface has @throws RuntimeException, implementation throws UnexpectedValueException (subclass) → no violation (exception hierarchy)
 */
final class LiskovSubstitutionPrincipleCheckerTest extends TestCase
{
    private static bool $examplesLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$examplesLoaded) {
            require_once __DIR__ . '/../liskov-principles-violation-example.php';
            self::$examplesLoaded = true;
        }
    }

    private function createChecker(): LiskovSubstitutionPrincipleChecker
    {
        return new LiskovSubstitutionPrincipleChecker(new ThrowsDetector());
    }

    public function testMyClass1HasViolations(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\MyClass1::class);

        $this->assertNotEmpty($violations, 'MyClass1 should violate LSP (throws RuntimeException, contract has no @throws)');
        $reasons = array_map(fn(LspViolation $v) => $v->reason, $violations);
        $this->assertStringContainsString('RuntimeException', implode(' ', $reasons));
    }

    public function testMyClass2HasNoViolations(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\MyClass2::class);

        $this->assertEmpty($violations, 'MyClass2 should not violate LSP (interface documents @throws RuntimeException)');
    }

    public function testMyClass2bHasNoViolationsWhenThrowingSubclassOfContractException(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\MyClass2b::class);

        $this->assertEmpty($violations, 'MyClass2b should not violate LSP (contract allows RuntimeException, implementation throws UnexpectedValueException which is a subclass)');
    }

    public function testMyClass3HasViolations(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\MyClass3::class);

        $this->assertNotEmpty($violations, 'MyClass3 should violate LSP (private method throws, contract has no @throws)');
        $reasons = implode(' ', array_map(fn(LspViolation $v) => $v->reason, $violations));
        $this->assertStringContainsString('RuntimeException', $reasons);
        $this->assertStringContainsString('InvalidArgumentException', $reasons);
    }

    public function testMyClass4HasViolationsFromAst(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\MyClass4::class);

        $this->assertNotEmpty($violations, 'MyClass4 should violate LSP (code throws, no @throws in contract; AST detection)');
        $reasons = implode(' ', array_map(fn(LspViolation $v) => $v->reason, $violations));
        $this->assertStringContainsString('RuntimeException', $reasons);
        $this->assertStringContainsString('AST', $reasons);
    }

    public function testMyClass5HasViolationsFromAst(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\MyClass5::class);

        $this->assertNotEmpty($violations, 'MyClass5 should violate LSP (private method throws, AST detection)');
        $reasons = implode(' ', array_map(fn(LspViolation $v) => $v->reason, $violations));
        $this->assertStringContainsString('RuntimeException', $reasons);
        $this->assertStringContainsString('AST', $reasons);
    }

    public function testMyClass6HasNoViolationsWithCovariantReturnType(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\MyClass6::class);

        $this->assertEmpty($violations, 'MyClass6 should not violate LSP (covariant return type is allowed)');
    }

    public function testMyClass7HasNoViolationsWithContravariantParameterType(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\MyClass7::class);

        $this->assertEmpty($violations, 'MyClass7 should not violate LSP (contravariant parameter type: RuntimeException → Exception is valid widening)');
    }

    public function testMyClass8HasNoViolationsWithIdenticalParameterTypes(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\MyClass8::class);

        $this->assertEmpty($violations, 'MyClass8 should not violate LSP (identical parameter types are trivially valid)');
    }

    /**
     * Test that the contravariance check detects a violation when the implementation
     * narrows a parameter type (e.g. contract accepts Exception, child accepts RuntimeException).
     *
     * Since PHP itself prevents loading classes with narrowed parameter types (fatal error),
     * we test the internal `isParameterTypeContravariant` method directly using ReflectionType
     * objects extracted from valid fixture classes.
     */
    public function testContravarianceDetectsViolationOnNarrowedParameterType(): void
    {
        $checker = $this->createChecker();

        // Extract ReflectionType for Exception (wide) and RuntimeException (narrow)
        $wideType = (new \ReflectionParameter([ContravariantFixtureWide::class, 'foo'], 'e'))->getType();
        $narrowType = (new \ReflectionParameter([ContravariantFixtureNarrow::class, 'foo'], 'e'))->getType();

        $wideContext = new \ReflectionClass(ContravariantFixtureWide::class);
        $narrowContext = new \ReflectionClass(ContravariantFixtureNarrow::class);

        $method = new \ReflectionMethod($checker, 'isParameterTypeContravariant');

        // Valid contravariance: contract=narrow (RuntimeException), class=wide (Exception) → true
        $this->assertTrue(
            $method->invoke($checker, $wideType, $narrowType, $wideContext, $narrowContext),
            'Exception (wider) should be contravariant with RuntimeException (narrower)'
        );

        // Invalid contravariance: contract=wide (Exception), class=narrow (RuntimeException) → false
        $this->assertFalse(
            $method->invoke($checker, $narrowType, $wideType, $narrowContext, $wideContext),
            'RuntimeException (narrower) should NOT be contravariant with Exception (wider)'
        );
    }

    /**
     * Test contravariance with untyped parameters.
     */
    public function testContravarianceWithUntypedParameters(): void
    {
        $checker = $this->createChecker();
        $method = new \ReflectionMethod($checker, 'isParameterTypeContravariant');

        $wideContext = new \ReflectionClass(ContravariantFixtureWide::class);
        $narrowContext = new \ReflectionClass(ContravariantFixtureNarrow::class);

        $typedException = (new \ReflectionParameter([ContravariantFixtureWide::class, 'foo'], 'e'))->getType();

        // Contract untyped, class untyped → valid
        $this->assertTrue(
            $method->invoke($checker, null, null, $wideContext, $narrowContext),
            'Both untyped → valid'
        );

        // Contract typed, class untyped → valid (widening to mixed)
        $this->assertTrue(
            $method->invoke($checker, null, $typedException, $wideContext, $narrowContext),
            'Contract typed, class untyped → valid widening'
        );

        // Contract untyped, class typed → violation (strengthening precondition)
        $this->assertFalse(
            $method->invoke($checker, $typedException, null, $wideContext, $narrowContext),
            'Contract untyped, class typed → violation'
        );
    }

    public function testAllExampleClassesAreCheckedWithoutReflectionException(): void
    {
        $classes = [\MyClass1::class, \MyClass2::class, \MyClass2b::class, \MyClass3::class, \MyClass4::class, \MyClass5::class, \MyClass6::class, \MyClass7::class, \MyClass8::class];
        $checker = $this->createChecker();

        foreach ($classes as $className) {
            $violations = $checker->check($className);
            $this->assertIsArray($violations);
            foreach ($violations as $v) {
                $this->assertInstanceOf(LspViolation::class, $v);
            }
        }
    }
}

// ---- Test fixture classes for contravariance unit tests ----
// These are standalone classes (not implementing any interface) so PHP does not enforce
// parameter compatibility. We use their ReflectionType objects to test the internal logic.

class ContravariantFixtureWide
{
    public function foo(\Exception $e): void {}
}

class ContravariantFixtureNarrow
{
    public function foo(\RuntimeException $e): void {}
}
