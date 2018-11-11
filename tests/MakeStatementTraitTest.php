<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Column;
use Finesse\QueryScribe\MakeStatementTrait;
use Finesse\QueryScribe\Raw;
use Finesse\QueryScribe\Value;

/**
 * Tests the TMakeRaw trait
 *
 * @author Surgie
 */
class MakeStatementTraitTest extends TestCase
{
    /**
     * Tests the raw method
     */
    public function testRaw()
    {
        /** @var Raw $raw */
        $obj = $this->createTestObject();
        $this->assertEquals(new Raw('`column` = ?', ['orange']), $obj->raw('`column` = ?', ['orange']));
        $this->assertEquals(new Value('John'), $obj->value('John'));
        $this->assertEquals(new Column('identity'), $obj->column('identity'));
    }

    /**
     * Creates an object with the TMakeRaw trait
     */
    protected function createTestObject()
    {
        return new class () {
            use MakeStatementTrait;
        };
    }
}
