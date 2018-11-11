<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Column;

/**
 * Tests the Column class
 *
 * @author Surgie
 */
class ColumnTest extends TestCase
{
    /**
     * Tests the StatementInterface methods
     */
    public function testStatement()
    {
        $value = new Column('name');
        $this->assertStatement('name', [], $value);

        $value = new Column('users.id');
        $this->assertStatement('users.id', [], $value);
    }
}
