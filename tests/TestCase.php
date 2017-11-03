<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\StatementInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base class for the tests.
 *
 * @author Surgie
 */
class TestCase extends BaseTestCase
{
    /**
     * Asserts that the given callback throws the given exception.
     *
     * @param string $expectClass The name of the expected exception class
     * @param callable $callback A callback which should throw the exception
     * @param callable|null $onException A function to call after exception check. It may be used to test the exception.
     */
    protected function assertException(string $expectClass, callable $callback, callable $onException = null)
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            $this->assertInstanceOf($expectClass, $exception);
            if ($onException) {
                $onException($exception);
            }
            return;
        }

        $this->fail('No exception has been thrown');
    }

    /**
     * Asserts that the given object has the given attributes with the given values.
     *
     * @param array $expectedAttributes Attributes. The indexes are the attributes names, the values are the attributes
     *    values.
     * @param mixed $actualObject Object
     */
    protected function assertAttributes(array $expectedAttributes, $actualObject)
    {
        foreach ($expectedAttributes as $property => $value) {
            $this->assertObjectHasAttribute($property, $actualObject);
            $this->assertAttributeEquals($value, $property, $actualObject);
        }
    }

    /**
     * Asserts that the given statement (raw SQL or compiled query) has the given SQL and the given bindings
     *
     * @param string $expectedSQL (may be human-formatted by spaces, tabs and new lines)
     * @param array $expectedBindings
     * @param StatementInterface $statement
     */
    protected function assertStatement(string $expectedSQL, array $expectedBindings, StatementInterface $statement)
    {
        $this->assertEquals($this->plainSQL($expectedSQL), $this->plainSQL($statement->getSQL()));
        $this->assertEquals($expectedBindings, $statement->getBindings());
    }

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
}
