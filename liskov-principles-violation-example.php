<?php

# ------------------------------------------------------------
# Example 1: Violation of Liskov Substitution Principle (Incorrect Implementation)
# ------------------------------------------------------------

interface MyInterface1
{
    public function doSomething(): void;
}

/**
 * This class violates the Liskov Substitution Principle.
 */
class MyClass1 implements MyInterface1
{
    /**
     * This method throws an exception, which violates the Liskov Substitution Principle.
     * The subclass should not throw an exception if the parent class does not throw an exception.
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
