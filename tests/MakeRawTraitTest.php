<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\MakeRawTrait;
use Finesse\QueryScribe\Raw;

/**
 * Tests the TMakeRaw trait
 *
 * @author Surgie
 */
class MakeRawTraitTest extends TestCase
{
    /**
     * Tests the raw method
     */
    public function testRaw()
    {
        /** @var Raw $raw */
        $obj = $this->createTestObject();
        $this->assertEquals(new Raw('`column` = ?', ['orange']), $obj->raw('`column` = ?', ['orange']));
    }

    /**
     * Creates an object with the TMakeRaw trait
     */
    protected function createTestObject()
    {
        return new class () {
            use MakeRawTrait;
        };
    }
}
