<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Value;

/**
 * Tests the Value class
 *
 * @author Surgie
 */
class ValueTest extends TestCase
{
    /**
     * Tests the StatementInterface methods
     */
    public function testStatement()
    {
        $value = new Value('once upon a time');
        $this->assertStatement('?', ['once upon a time'], $value);

        $value = new Value(null);
        $this->assertStatement('?', [null], $value);
    }
}
