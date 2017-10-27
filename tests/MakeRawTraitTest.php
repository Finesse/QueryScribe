<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Common\MakeRawTrait;
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
        $raw = $obj->raw('`column` = ?', ['orange']);
        $this->assertInstanceOf(Raw::class, $raw);
        $this->assertEquals($this->plainSQL('`column` = ?'), $this->plainSQL($raw->getSQL()));
        $this->assertEquals(['orange'], $raw->getBindings());
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
