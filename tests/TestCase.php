<?php

namespace Finesse\QueryScribe\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base class for the tests.
 *
 * @author Surgie
 */
class TestCase extends BaseTestCase
{
    /**
     * Converts a SQL text to a single string with no double spaces.
     *
     * @param string $sql
     * @return string
     */
    protected function plainSQL(string $sql): string
    {
        $sql = preg_replace('/\s*([,()])\s*/', '$1', $sql);
        return trim(preg_replace('/\s+/', ' ', $sql));
    }

    /**
     * Asserts that the given callback throws the given exception.
     *
     * @param string $expectClass The name of the expected exception class
     * @param callable $callback A callback which should throw the exception
     */
    protected function assertException(string $expectClass, callable $callback)
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            $this->assertInstanceOf($expectClass, $exception);
            return;
        }

        $this->fail('No exception was thrown');
    }
}
