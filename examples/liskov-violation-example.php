<?php

# ------------------------------------------------------------
# Example 1: Violation of Liskov Substitution Principle (Incorrect Implementation)
# ------------------------------------------------------------

interface MyInterface1
{
    /**
     * This method does not mention (docblock) throwing an exception. Subclasses must not throw any exceptions.
     */
    public function doSomething(): void;
}

/**
 * This class violates the Liskov Substitution Principle.
 */
class MyClass1 implements MyInterface1
{
    /**
     * This method throws an exception, which violates the Liskov Substitution Principle.
     * The subclass should not throw an exception if the parent class does not mention throwing an exception.
     * @throws RuntimeException
     */
    public function doSomething(): void
    {
        throw new RuntimeException("exception is thrown");
    }
}

# ------------------------------------------------------------
# Example 2: No Violation of Liskov Substitution Principle (Correct Implementation)
# ------------------------------------------------------------

interface MyInterface2
{
    /**
     * @throws RuntimeException
     */
    public function doSomething(): void;
}

/**
 * This interface does not violate the Liskov Substitution Principle.
 */
class MyClass2 implements MyInterface2
{
    /**
     * This method throws an exception, which does NOT violate the Liskov Substitution Principle.
     * The interface (MyInterface2) documents @throws RuntimeException, so the contract allows it.
     * @throws RuntimeException
     */
    public function doSomething(): void
    {
        throw new RuntimeException("exception is thrown");
    }
}

# ------------------------------------------------------------
# Example 2b: Exception hierarchy — contract allows RuntimeException, implementation throws subclass (LSP-compliant)
# In PHP, UnexpectedValueException extends RuntimeException.
# ------------------------------------------------------------

interface MyInterface2b
{
    /**
     * @throws RuntimeException
     */
    public function doSomething(): void;
}

/**
 * Throwing UnexpectedValueException (subclass of RuntimeException) is allowed by the contract.
 */
class MyClass2b implements MyInterface2b
{
    /**
     * @throws \UnexpectedValueException
     */
    public function doSomething(): void
    {
        throw new \UnexpectedValueException("unexpected value");
    }
}

# ------------------------------------------------------------
# Example 3: Violation of Liskov Substitution Principle (Incorrect Implementation)
# ------------------------------------------------------------

interface MyInterface3
{
    public function doSomething(): void;
}

class MyClass3 implements MyInterface3
{
    /**
     * This method calls a private method that throws an exception, which violates the Liskov Substitution Principle.
     * The subclass should not call a private method of the parent class.
     * @throws RuntimeException|InvalidArgumentException
     */
    public function doSomething(): void
    {
        $this->doSomethingPrivate();
    }

    /**
     * This private method throws an exception, which violates the Liskov Substitution Principle.
     * The private method should not be called by the subclass if the parent class does not throw an exception.
     * @throws RuntimeException|InvalidArgumentException
     */
    private function doSomethingPrivate(): void
    {
        if (rand(0, 1) === 1) {
            throw new RuntimeException("runtime exception is thrown");
        } else {
            throw new InvalidArgumentException("another exception is thrown");
        }
    }
}

# ------------------------------------------------------------
# Example 4: Violation detected only via AST (no @throws docblock)
# The developer forgot to document the exception, but the code throws it.
# Only AST analysis can catch this violation.
# ------------------------------------------------------------

interface MyInterface4
{
    public function process(): void;
}

class MyClass4 implements MyInterface4
{
    /**
     * This method throws an exception but has no @throws docblock.
     * Docblock-only analysis would miss this violation entirely.
     * AST analysis detects the actual throw statement.
     */
    public function process(): void
    {
        if (!file_exists('/tmp/required-file')) {
            throw new RuntimeException("Required file not found");
        }
    }
}

# ------------------------------------------------------------
# Example 5: Violation of Liskov Substitution Principle (Incorrect Implementation)
# The developer forgot to document the exception, but the code throws it via a private method.
# Only AST analysis can catch this violation.
# ------------------------------------------------------------

interface MyInterface5
{
    public function process(): void;
}

class MyClass5 implements MyInterface5
{
    /**
     * This method throws an exception but has no @throws docblock.
     * Docblock-only analysis would miss this violation entirely.
     * AST analysis detects the actual throw statement.
     */
    public function process(): void
    {
        $this->doSomething();
    }

    private function doSomething(): void
    {
        if (!file_exists('/tmp/required-file')) {
            throw new RuntimeException("Required file not found");
        }
    }
}

# ------------------------------------------------------------
# Example 6: Return type covariance (LSP-compliant)
# Contract returns RuntimeException, implementation returns a subtype.
# ------------------------------------------------------------

interface MyInterface6
{
    public function createException(): RuntimeException;
}

class MyClass6 implements MyInterface6
{
    public function createException(): UnexpectedValueException
    {
        return new UnexpectedValueException("more specific return type");
    }
}

# ------------------------------------------------------------
# Example 7: Parameter type contravariance (LSP-compliant)
# Contract accepts RuntimeException, implementation widens to Exception (supertype).
# This is valid contravariance: the implementation accepts a wider range of inputs.
# ------------------------------------------------------------

interface MyInterface7
{
    public function handleException(RuntimeException $e): void;
}

class MyClass7 implements MyInterface7
{
    public function handleException(Exception $e): void
    {
        // valid: widening from RuntimeException to Exception (contravariant)
    }
}

# ------------------------------------------------------------
# Example 8: Parameter type contravariance with identical types (trivially LSP-compliant)
# Both contract and implementation use the same type — always valid.
# Also tests multiple parameters.
# ------------------------------------------------------------

interface MyInterface8
{
    public function transform(string $input, int $flags): string;
}

class MyClass8 implements MyInterface8
{
    public function transform(string $input, int $flags): string
    {
        return strtoupper($input);
    }
}

# ------------------------------------------------------------
# Example 9: Exception thrown in another class
# The exception is thrown in a static method of another class, which is not allowed.
# Only AST analysis can catch this violation.
# ------------------------------------------------------------

interface MyInterface9
{
    public function doSomething(): void;
}

class MyClass9Helper
{
    public static function doSomethingRisky(): void
    {
        throw new RuntimeException("runtime exception is thrown");
    }
}

class MyClass9 implements MyInterface9
{
    public function doSomething(): void
    {
        MyClass9Helper::doSomethingRisky();
    }
}

# ------------------------------------------------------------
# Example 10: Exception thrown in another class
# The exception is thrown in an instance method of another class, called via (new Helper())->method().
# Only AST analysis can catch this violation.
# ------------------------------------------------------------

interface MyInterface10
{
    public function doSomething(): void;
}
class MyClass10Helper
{
    public function doSomethingRisky(): void
    {
        throw new RuntimeException("runtime exception is thrown");
    }
}
class MyClass10 implements MyInterface10
{
    public function doSomething(): void
    {
        (new MyClass10Helper())->doSomethingRisky();
    }
}

# ------------------------------------------------------------  
# Example 11: Violation of Liskov Substitution Principle (Incorrect Implementation)
# ------------------------------------------------------------

interface MyInterface11
{
    public function doSomething(): void;
}

/**
 * This trait violates the Liskov Substitution Principle.
 */
trait MyTrait11 {
    /**
     * This method throws an exception, which violates the Liskov Substitution Principle.
     * The trait should not throw an exception if the interface does not mention throwing an exception.
     */
    public function doSomething(): void
    {
        throw new RuntimeException("runtime exception is thrown");
    }
}

class MyClass11 implements MyInterface11
{
    /**
     * The trait MyTrait11 throws an exception, which violates the Liskov Substitution Principle.
     * The subclass should not throw an exception if the trait does not mention throwing an exception.
     */
    use MyTrait11;
}

# ------------------------------------------------------------
# Example 12: Exception thrown via dynamic method call on variable
# The exception is thrown by a method on an object stored in a variable ($helper->doSomethingRisky()).
# Only AST analysis with variable type resolution (parameter type) can catch this violation.
# ------------------------------------------------------------

interface MyInterface12
{
    public function doSomething(MyClass12Helper $helper): void;
}

class MyClass12Helper
{
    public function doSomethingRisky(): void
    {
        throw new RuntimeException("runtime exception is thrown");
    }
}

class MyClass12 implements MyInterface12
{
    public function doSomething(MyClass12Helper $helper): void
    {
        $helper->doSomethingRisky();
    }
}
