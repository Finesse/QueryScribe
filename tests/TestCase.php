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
}
