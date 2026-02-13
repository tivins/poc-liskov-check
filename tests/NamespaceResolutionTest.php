<?php

declare(strict_types=1);

namespace Tivins\LSP\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tivins\LSP\LiskovSubstitutionPrincipleChecker;
use Tivins\LSP\LspViolation;
use Tivins\LSP\ThrowsDetector;

/**
 * Unit tests for namespace resolution in the LSP checker and ThrowsDetector.
 *
 * Validates that FQCN resolution works correctly when classes and interfaces
 * are defined in namespaces, using both docblock (@throws) and AST detection.
 *
 * Scenarios covered:
 * - FQCN in @throws (same namespace)
 * - Short exception name (same namespace, string match)
 * - Custom namespaced exception with FQCN
 * - Subclass of a namespaced exception (hierarchy check)
 * - use import + short name in throw statement (AST NameResolver)
 * - Violations: no @throws in contract, AST-only detection, transitive throws
 * - Cross-namespace contracts with namespaced exception FQCNs
 */
final class NamespaceResolutionTest extends TestCase
{
    private static bool $fixtureLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$fixtureLoaded) {
            require_once __DIR__ . '/fixtures/namespace-resolution-fixture.php';
            self::$fixtureLoaded = true;
        }
    }

    private function createChecker(): LiskovSubstitutionPrincipleChecker
    {
        return new LiskovSubstitutionPrincipleChecker(new ThrowsDetector());
    }

    // ================================================================
    //  Checker tests: full check() with namespaced classes
    // ================================================================

    /**
     * Scenario 1: Both contract and class in the same namespace use FQCN (\RuntimeException)
     * in @throws. The leading \ is stripped, then both resolve consistently → no violation.
     */
    public function testFqcnInThrowsSameNamespaceNoViolation(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassFqcn::class);

        $this->assertEmpty(
            $violations,
            'No violation expected when FQCN is used in @throws for both contract and class in the same namespace'
        );
    }

    /**
     * Scenario 2: Both contract and class in the same namespace use a short exception name
     * ("RuntimeException") in @throws. Both resolve to the same namespace-relative FQCN → no violation.
     */
    public function testShortNameSameNamespaceNoViolation(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassShortName::class);

        $this->assertEmpty(
            $violations,
            'No violation expected when short exception name is used in same namespace for both contract and class'
        );
    }

    /**
     * Scenario 3: Both contract and class use the FQCN of a custom namespaced exception.
     * Since the path contains \, resolveExceptionFqcn treats it as a true FQCN → no violation.
     */
    public function testCustomExceptionFqcnNoViolation(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassCustomFqcn::class);

        $this->assertEmpty(
            $violations,
            'No violation expected when custom namespaced exception is used with FQCN in both contract and class'
        );
    }

    /**
     * Scenario 4: Contract allows CustomNsException; class throws SubCustomNsException (a subclass).
     * Exception hierarchy is resolved correctly with FQCNs → LSP-compliant, no violation.
     */
    public function testSubclassOfCustomExceptionNoViolation(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassCustomSubclass::class);

        $this->assertEmpty(
            $violations,
            'No violation expected when throwing a subclass of the contract-allowed custom namespaced exception'
        );
    }

    /**
     * Scenario 5: Class uses a short name in `throw new CustomNsException()` resolved
     * via a `use` import. AST NameResolver expands it to the FQCN, matching the contract → no violation.
     */
    public function testUseImportShortNameInCodeNoViolation(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassUseImport::class);

        $this->assertEmpty(
            $violations,
            'No violation expected when use import resolves short name in throw statement to correct FQCN'
        );
    }

    /**
     * Scenario 6: Contract has no @throws, but the namespaced class declares and throws
     * \RuntimeException → violation detected (both docblock and AST).
     */
    public function testNoThrowsInContractNamespacedClassIsViolation(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassThrowsInNs::class);

        $this->assertNotEmpty(
            $violations,
            'Violation expected when contract has no @throws but namespaced class throws'
        );
        $reasons = implode(' ', array_map(fn(LspViolation $v) => $v->reason, $violations));
        $this->assertStringContainsString('RuntimeException', $reasons);
    }

    /**
     * Scenario 7: No @throws docblock on the class, but code throws \RuntimeException
     * (AST-only detection in a namespaced class) → violation detected.
     */
    public function testAstOnlyDetectionInNamespaceIsViolation(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassAstOnlyNs::class);

        $this->assertNotEmpty(
            $violations,
            'Violation expected: AST detects throw in namespaced class without @throws in contract'
        );
        $reasons = implode(' ', array_map(fn(LspViolation $v) => $v->reason, $violations));
        $this->assertStringContainsString('RuntimeException', $reasons);
        $this->assertStringContainsString('AST', $reasons);
    }

    /**
     * Scenario 8: Class implements a contract from a different namespace.
     * Both use FQCN for a custom namespaced exception (path contains \) → resolves correctly → no violation.
     */
    public function testCrossNamespaceWithFqcnNoViolation(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassCrossNamespace::class);

        $this->assertEmpty(
            $violations,
            'No violation expected when class in different namespace from contract uses FQCN for namespaced exception'
        );
    }

    /**
     * Scenario 9: Transitive throw via $this->internalMethod() in a namespaced class.
     * The private method throws a custom exception (resolved via use import) → violation detected.
     */
    public function testTransitiveThrowInNamespaceIsViolation(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassTransitiveNs::class);

        $this->assertNotEmpty(
            $violations,
            'Violation expected: transitive throw via $this->method() in namespaced class'
        );
        $reasons = implode(' ', array_map(fn(LspViolation $v) => $v->reason, $violations));
        $this->assertStringContainsString('CustomNsException', $reasons);
        $this->assertStringContainsString('AST', $reasons);
    }

    // ================================================================
    //  Checker tests: global exception resolution in namespaces
    // ================================================================

    /**
     * Scenario 10: Contract allows RuntimeException (short name), class throws
     * UnexpectedValueException (a subclass). In a namespace, "RuntimeException" must be
     * resolved to \RuntimeException so that is_subclass_of works correctly.
     */
    public function testHierarchyWithGlobalExceptionsInNamespace(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassHierarchySubclassNs::class);

        $this->assertEmpty(
            $violations,
            'No violation expected: UnexpectedValueException is a subclass of RuntimeException; '
            . 'short name "RuntimeException" must resolve to \RuntimeException for hierarchy check to work'
        );
    }

    /**
     * Scenario 11: Contract in OtherNs and class in NsResolution both declare
     * @throws RuntimeException (short name). Both must resolve to \RuntimeException
     * regardless of their respective namespaces.
     */
    public function testCrossNamespaceShortNameGlobalExceptionNoViolation(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassCrossNsShortName::class);

        $this->assertEmpty(
            $violations,
            'No violation expected: short name "RuntimeException" in different namespaces must both resolve to \RuntimeException'
        );
    }

    /**
     * Scenario 12: @throws Exception (short name) in a namespace must be understood
     * as \Exception, not \Namespace\Exception.
     */
    public function testShortNameExceptionResolvedToGlobalInNamespace(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassThrowsException::class);

        $this->assertEmpty(
            $violations,
            'No violation expected: "Exception" in a namespace must resolve to \Exception'
        );
    }

    /**
     * Scenario 13: Contract allows Exception (short name), class throws RuntimeException
     * (subclass of \Exception). Hierarchy check must work: \RuntimeException extends \Exception.
     */
    public function testHierarchyShortNameExceptionAllowsSubclass(): void
    {
        $checker = $this->createChecker();
        $violations = $checker->check(\Tivins\LSP\Tests\Fixtures\NsResolution\ClassThrowsSubclassOfException::class);

        $this->assertEmpty(
            $violations,
            'No violation expected: RuntimeException is a subclass of Exception; '
            . 'short name "Exception" must resolve to \Exception for hierarchy check to work'
        );
    }

    // ================================================================
    //  ThrowsDetector tests: AST resolution with namespaces
    // ================================================================

    /**
     * getActualThrows should resolve a `use`-imported short name to the full FQCN
     * via the AST NameResolver.
     */
    public function testActualThrowsResolvesUseImportToFqcn(): void
    {
        $detector = new ThrowsDetector();
        $method = new ReflectionMethod(
            \Tivins\LSP\Tests\Fixtures\NsResolution\ClassUseImport::class,
            'execute'
        );

        $actualThrows = $detector->getActualThrows($method);

        $this->assertContains(
            'Tivins\LSP\Tests\Fixtures\NsResolution\Exceptions\CustomNsException',
            $actualThrows,
            'AST should resolve use-imported short name to full FQCN (without leading backslash)'
        );
    }

    /**
     * getActualThrows should resolve \RuntimeException (explicit global FQCN in code)
     * to "RuntimeException" (normalized without leading \).
     */
    public function testActualThrowsResolvesGlobalExceptionFqcn(): void
    {
        $detector = new ThrowsDetector();
        $method = new ReflectionMethod(
            \Tivins\LSP\Tests\Fixtures\NsResolution\ClassFqcn::class,
            'execute'
        );

        $actualThrows = $detector->getActualThrows($method);

        $this->assertContains(
            'RuntimeException',
            $actualThrows,
            'AST should resolve \RuntimeException to "RuntimeException" (global, normalized)'
        );
    }

    /**
     * getActualThrows should follow $this->method() calls transitively
     * and resolve the thrown namespaced exception to its FQCN.
     */
    public function testActualThrowsTransitiveInNamespaceResolvesCorrectly(): void
    {
        $detector = new ThrowsDetector();
        $method = new ReflectionMethod(
            \Tivins\LSP\Tests\Fixtures\NsResolution\ClassTransitiveNs::class,
            'execute'
        );

        $actualThrows = $detector->getActualThrows($method);

        $this->assertContains(
            'Tivins\LSP\Tests\Fixtures\NsResolution\Exceptions\CustomNsException',
            $actualThrows,
            'AST should resolve transitive throw to FQCN via use import in namespace'
        );
    }

    /**
     * getDeclaredThrows should preserve the FQCN (without leading \) from the docblock
     * for a custom namespaced exception.
     */
    public function testDeclaredThrowsFqcnPreservesNamespacedPath(): void
    {
        $detector = new ThrowsDetector();
        $method = new ReflectionMethod(
            \Tivins\LSP\Tests\Fixtures\NsResolution\ClassCustomFqcn::class,
            'execute'
        );

        $declaredThrows = $detector->getDeclaredThrows($method);

        $this->assertContains(
            'Tivins\LSP\Tests\Fixtures\NsResolution\Exceptions\CustomNsException',
            $declaredThrows,
            'getDeclaredThrows should preserve FQCN (without leading backslash) from docblock'
        );
    }

    /**
     * getDeclaredThrows returns the short name as-is from the docblock —
     * it does not resolve use imports or namespaces in docblock text.
     */
    public function testDeclaredThrowsShortNameInNamespaceKeptAsIs(): void
    {
        $detector = new ThrowsDetector();
        $method = new ReflectionMethod(
            \Tivins\LSP\Tests\Fixtures\NsResolution\ClassShortName::class,
            'execute'
        );

        $declaredThrows = $detector->getDeclaredThrows($method);

        $this->assertContains(
            'RuntimeException',
            $declaredThrows,
            'getDeclaredThrows should return short name as-is (no namespace resolution in docblock parsing)'
        );
    }
}
