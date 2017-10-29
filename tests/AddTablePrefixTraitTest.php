<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\AddTablePrefixTrait;

/**
 * Tests the TAddTablePrefix trait
 *
 * @author Surgie
 */
class AddTablePrefixTraitTest extends TestCase
{
    /**
     * Tests the addTablePrefixMethod method
     */
    public function testAddTablePrefix()
    {
        // With prefix
        $obj = $this->createTestObject('prefix_');
        $this->assertEquals('prefix_tab1', $obj->addTablePrefix('tab1'));
        $this->assertEquals('database.prefix_table', $obj->addTablePrefix('database.table'));

        // Empty prefix
        $obj = $this->createTestObject('');
        $this->assertEquals('tab1', $obj->addTablePrefix('tab1'));
        $this->assertEquals('database.table', $obj->addTablePrefix('database.table'));
    }


    /**
     * Tests the addTablePrefixToColumn method
     */
    public function testAddTablePrefixToColumn()
    {
        // With prefix
        $obj = $this->createTestObject('demo__');
        $this->assertEquals('column1', $obj->addTablePrefixToColumn('column1'));
        $this->assertEquals('demo__table.column1', $obj->addTablePrefixToColumn('table.column1'));
        $this->assertEquals('database.demo__table.column1', $obj->addTablePrefixToColumn('database.table.column1'));

        // Empty prefix
        $obj = $this->createTestObject('');
        $this->assertEquals('column1', $obj->addTablePrefixToColumn('column1'));
        $this->assertEquals('table.column1', $obj->addTablePrefixToColumn('table.column1'));
        $this->assertEquals('database.table.column1', $obj->addTablePrefixToColumn('database.table.column1'));
    }

    /**
     * Creates an object with the TAddTablePrefix trait
     */
    protected function createTestObject(string $prefix)
    {
        return new class ($prefix) {
            use AddTablePrefixTrait;
            public function __construct($prefix)
            {
                $this->tablePrefix = $prefix;
            }
        };
    }
}
