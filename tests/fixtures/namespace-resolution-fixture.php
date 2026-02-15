<?php

/**
 * Fixture for namespace resolution tests.
 *
 * Validates that exception names are correctly resolved when classes
 * and interfaces are defined in PHP namespaces, using both docblock
 * (@throws) and AST detection paths.
 *
 * Uses bracketed namespace syntax to define multiple namespaces in one file.
 */

namespace Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions {

    class CustomNsException extends \RuntimeException
    {
    }

    class SubCustomNsException extends CustomNsException
    {
    }
}

namespace Tivins\Solid\Tests\Fixtures\NsResolution\OtherNs {

    /**
     * Contract in a different namespace (used to test cross-namespace FQCN resolution).
     */
    interface ContractOtherNs
    {
        /**
         * @throws \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\CustomNsException
         */
        public function execute(): void;
    }

    /**
     * Contract in a different namespace using a short name for a global exception.
     * Used to test that "RuntimeException" is correctly resolved to \RuntimeException
     * even when the contract is in a different namespace than the class.
     */
    interface ContractOtherNsShortName
    {
        /**
         * @throws RuntimeException
         */
        public function execute(): void;
    }
}

namespace Tivins\Solid\Tests\Fixtures\NsResolution {

    use Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\CustomNsException;

    // ---------------------------------------------------------------
    // Scenario 1: FQCN (\RuntimeException) in @throws, same namespace
    // Both contract and class use FQCN → resolved consistently → no violation
    // ---------------------------------------------------------------

    interface ContractFqcn
    {
        /**
         * @throws \RuntimeException
         */
        public function execute(): void;
    }

    class ClassFqcn implements ContractFqcn
    {
        /**
         * @throws \RuntimeException
         */
        public function execute(): void
        {
            throw new \RuntimeException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 2: Short exception name in @throws, same namespace
    // Both contract and class use "RuntimeException" → same resolution → no violation
    // ---------------------------------------------------------------

    interface ContractShortName
    {
        /**
         * @throws RuntimeException
         */
        public function execute(): void;
    }

    class ClassShortName implements ContractShortName
    {
        /**
         * @throws RuntimeException
         */
        public function execute(): void
        {
            throw new \RuntimeException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 3: Custom namespaced exception with full FQCN in @throws
    // Both sides use the same full path → no violation
    // ---------------------------------------------------------------

    interface ContractCustomFqcn
    {
        /**
         * @throws \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\CustomNsException
         */
        public function execute(): void;
    }

    class ClassCustomFqcn implements ContractCustomFqcn
    {
        /**
         * @throws \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\CustomNsException
         */
        public function execute(): void
        {
            throw new \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\CustomNsException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 4: Subclass of a custom namespaced exception
    // Contract allows CustomNsException, class throws SubCustomNsException → LSP-compliant
    // ---------------------------------------------------------------

    interface ContractCustomParent
    {
        /**
         * @throws \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\CustomNsException
         */
        public function execute(): void;
    }

    class ClassCustomSubclass implements ContractCustomParent
    {
        /**
         * @throws \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\SubCustomNsException
         */
        public function execute(): void
        {
            throw new \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\SubCustomNsException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 5: "use" import + short name in throw statement
    // The `use CustomNsException` at namespace level allows `throw new CustomNsException()`
    // AST NameResolver resolves the short name to the full FQCN
    // ---------------------------------------------------------------

    interface ContractUseImport
    {
        /**
         * @throws \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\CustomNsException
         */
        public function execute(): void;
    }

    class ClassUseImport implements ContractUseImport
    {
        /**
         * @throws \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\CustomNsException
         */
        public function execute(): void
        {
            // Uses short name thanks to `use CustomNsException` at namespace block level.
            // AST NameResolver should resolve this to the full FQCN.
            throw new CustomNsException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 6: Contract has no @throws, class throws in namespace → violation
    // ---------------------------------------------------------------

    interface ContractNoThrowsNs
    {
        public function execute(): void;
    }

    class ClassThrowsInNs implements ContractNoThrowsNs
    {
        /**
         * @throws \RuntimeException
         */
        public function execute(): void
        {
            throw new \RuntimeException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 7: No @throws docblock, but code throws (AST-only) in namespace → violation
    // ---------------------------------------------------------------

    class ClassAstOnlyNs implements ContractNoThrowsNs
    {
        public function execute(): void
        {
            throw new \RuntimeException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 8: Cross-namespace — class implements contract from another namespace
    // Both use FQCN for a custom exception (contains \) → resolves correctly → no violation
    // ---------------------------------------------------------------

    class ClassCrossNamespace implements \Tivins\Solid\Tests\Fixtures\NsResolution\OtherNs\ContractOtherNs
    {
        /**
         * @throws \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\CustomNsException
         */
        public function execute(): void
        {
            throw new CustomNsException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 9: Transitive throw via $this->method() in namespace
    // Private method throws a namespaced exception resolved via use import
    // ---------------------------------------------------------------

    interface ContractTransitiveNs
    {
        public function execute(): void;
    }

    class ClassTransitiveNs implements ContractTransitiveNs
    {
        public function execute(): void
        {
            $this->internalMethod();
        }

        private function internalMethod(): void
        {
            throw new CustomNsException('transitive');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 10: Exception hierarchy with global exceptions in namespace
    // Contract allows RuntimeException (short name), class throws
    // UnexpectedValueException (subclass of RuntimeException).
    // Verifies that "RuntimeException" is resolved to \RuntimeException
    // so that is_subclass_of works correctly.
    // ---------------------------------------------------------------

    interface ContractHierarchyGlobalNs
    {
        /**
         * @throws RuntimeException
         */
        public function execute(): void;
    }

    class ClassHierarchySubclassNs implements ContractHierarchyGlobalNs
    {
        /**
         * @throws \UnexpectedValueException
         */
        public function execute(): void
        {
            throw new \UnexpectedValueException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 11: Cross-namespace with short name for global exception
    // Contract in OtherNs has @throws RuntimeException, class in NsResolution
    // also has @throws RuntimeException. Both should resolve to \RuntimeException.
    // ---------------------------------------------------------------

    class ClassCrossNsShortName implements \Tivins\Solid\Tests\Fixtures\NsResolution\OtherNs\ContractOtherNsShortName
    {
        /**
         * @throws RuntimeException
         */
        public function execute(): void
        {
            throw new \RuntimeException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 12: Short name "Exception" in namespace resolved to \Exception
    // Verifies the most basic case: @throws Exception is understood
    // as \Exception, not \Namespace\Exception.
    // ---------------------------------------------------------------

    interface ContractThrowsException
    {
        /**
         * @throws Exception
         */
        public function execute(): void;
    }

    class ClassThrowsException implements ContractThrowsException
    {
        /**
         * @throws Exception
         */
        public function execute(): void
        {
            throw new \Exception('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 13: Contract allows Exception (short name), class throws
    // RuntimeException (subclass of \Exception). Hierarchy must work.
    // ---------------------------------------------------------------

    class ClassThrowsSubclassOfException implements ContractThrowsException
    {
        /**
         * @throws RuntimeException
         */
        public function execute(): void
        {
            throw new \RuntimeException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 14: Contract uses short name for a custom exception
    // imported via `use`. The @throws tag says "CustomNsException"
    // (not the FQCN), relying on the `use` import at the top of this
    // namespace block. The checker must resolve the short name through
    // the use import table to avoid a false positive.
    // ---------------------------------------------------------------

    interface ContractShortCustomException
    {
        /**
         * @throws CustomNsException
         */
        public function execute(): void;
    }

    class ClassThrowsShortCustomException implements ContractShortCustomException
    {
        /**
         * @throws CustomNsException
         */
        public function execute(): void
        {
            throw new CustomNsException('test');
        }
    }

    // ---------------------------------------------------------------
    // Scenario 15: Contract uses short name for a custom exception
    // via `use` import. Class throws a subclass of that exception.
    // Hierarchy check must work even when the contract uses a short name.
    // ---------------------------------------------------------------

    class ClassThrowsSubOfShortCustomException implements ContractShortCustomException
    {
        /**
         * @throws \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\SubCustomNsException
         */
        public function execute(): void
        {
            throw new \Tivins\Solid\Tests\Fixtures\NsResolution\Exceptions\SubCustomNsException('test');
        }
    }
}
