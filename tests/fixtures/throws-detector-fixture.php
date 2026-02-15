<?php

namespace Tivins\Solid\Tests\Fixtures;

class ThrowsDetectorFixture
{
    public function noDocblock(): void
    {
    }

    /**
     * @throws RuntimeException
     */
    public function singleThrows(): void
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function fqcnThrows(): void
    {
    }

    /**
     * @throws RuntimeException|InvalidArgumentException
     */
    public function pipeThrows(): void
    {
    }

    /**
     * @throws RuntimeException Description with spaces and more text.
     */
    public function throwsWithDescription(): void
    {
    }

    /**
     * Method that actually throws (for getActualThrows).
     * @throws RuntimeException
     */
    public function actualThrow(): void
    {
        throw new \RuntimeException('test');
    }

    /**
     * Re-throw in catch (for getActualThrows).
     */
    public function rethrowInCatch(): void
    {
        try {
            throw new \InvalidArgumentException('x');
        } catch (\InvalidArgumentException $e) {
            throw $e;
        }
    }

    /**
     * Calls private method that throws (transitive).
     */
    public function callsPrivateThatThrows(): void
    {
        $this->privateThrows();
    }

    private function privateThrows(): void
    {
        throw new \DomainException('private');
    }
}
