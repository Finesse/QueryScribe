<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\BuilderFactory;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\Raw;

/**
 * Tests the BuilderFactory class
 *
 * @author Surgie
 */
class BuilderFactoryTest extends TestCase
{
    /**
     * Tests the builder method
     */
    public function testBuilder()
    {
        $builderFactory = new BuilderFactory(null, 'big_');
        $query = $builderFactory->builder();
        $this->assertInstanceOf(Query::class, $query);
        $this->assertNull($query->from);
        $this->assertEquals('big_boss', $query->addTablePrefix('boss'));
    }

    /**
     * Tests the table method
     */
    public function testTable()
    {
        $builderFactory = new BuilderFactory(null, 'big_');
        $query = $builderFactory->table('boss');
        $this->assertInstanceOf(Query::class, $query);
        $this->assertEquals($query->from, 'big_boss');
        $this->assertEquals('big_cat', $query->addTablePrefix('cat'));
    }

    /**
     * Tests that the trait methods are available
     */
    public function testTraits()
    {
        $builderFactory = new BuilderFactory(null, 'prefix_');

        $this->assertEquals('prefix_table', $builderFactory->addTablePrefix('table'));

        $raw = $builderFactory->raw('`column` = ?', ['orange']);
        $this->assertInstanceOf(Raw::class, $raw);
        $this->assertEquals($this->plainSQL('`column` = ?'), $this->plainSQL($raw->getSQL()));
        $this->assertEquals(['orange'], $raw->getBindings());
    }
}
