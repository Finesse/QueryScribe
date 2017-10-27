<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\BuilderFactory;
use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidQueryException;
use Finesse\QueryScribe\Raw;

/**
 * Tests the Query class. Also tests the SQL compiling.
 *
 * @author Surgie
 */
class QueryTest extends TestCase
{
    /**
     * Tests the select queries
     */
    public function testSelect()
    {
        $builderFactory = new BuilderFactory(null, 'pref_');

        // Simple
        $compiled = $builderFactory->table('items')->get();
        $this->assertEquals($this->plainSQL('SELECT * FROM `pref_items`'), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Specify a column
        $compiled = $builderFactory->table('items')->select('items.id')->get();
        $this->assertEquals($this->plainSQL('SELECT `pref_items`.`id` FROM `pref_items`'), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Specify a column with alias
        $compiled = $builderFactory->table('items')->select('name', 'n')->get();
        $this->assertEquals($this->plainSQL('SELECT `name` AS n FROM `pref_items`'), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Specify columns
        $compiled = $builderFactory->table('items')->select(['id', 'p' => 'price'])->addSelect(['q' => 'quantity'])->get();
        $this->assertEquals($this->plainSQL('
            SELECT `id`, `price` AS p, `quantity` AS q 
            FROM `pref_items`
        '), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Specify column as a raw SQL
        $compiled = $builderFactory->table('items')->select(new Raw('SELECT id, ? AS value FROM temp', [13]))->get();
        $this->assertEquals($this->plainSQL('
            SELECT (
                SELECT id, ? AS value
                FROM temp
            )
            FROM `pref_items`
        '), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([13], $compiled->getBindings());

        // Wipe the columns list using the select method
        $compiled = $builderFactory->table('items')->addSelect(['quantity'])->select(['id', 'price'])->get();
        $this->assertEquals($this->plainSQL('
            SELECT `id`, `price`
            FROM `pref_items`
        '), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Incorrect from argument error
        $this->assertException(InvalidArgumentException::class, function () use ($builderFactory) {
            $builderFactory->builder()->from(['foo', 'bar'])->get();
        });

        // Incorrect select arguments error
        $this->assertException(InvalidArgumentException::class, function () use ($builderFactory) {
            $builderFactory->table('table')->select(new \stdClass())->get();
        });
        $this->assertException(InvalidArgumentException::class, function () use ($builderFactory) {
            $builderFactory->table('table')->select(['foo' => [1, 2, 3]])->get();
        });

        // No from error
        $this->assertException(InvalidQueryException::class, function () use ($builderFactory) {
            $builderFactory->builder()->select(['id', 'name'])->get();
        });
    }

    /**
     * Tests that the trait methods are available
     */
    public function testTraits()
    {
        $query = (new BuilderFactory(null, 'prefix_'))->builder();

        $this->assertEquals('prefix_table', $query->addTablePrefix('table'));

        $raw = $query->raw('`column` = ?', ['orange']);
        $this->assertInstanceOf(Raw::class, $raw);
        $this->assertEquals($this->plainSQL('`column` = ?'), $this->plainSQL($raw->getSQL()));
        $this->assertEquals(['orange'], $raw->getBindings());
    }
}
