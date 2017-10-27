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
        $builder = new BuilderFactory(null, 'pref_');

        // Simple
        $compiled = $builder->table('items')->get();
        $this->assertEquals($this->plainSQL('SELECT * FROM `pref_items`'), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Specify a column
        $compiled = $builder->table('items')->select('items.id')->get();
        $this->assertEquals($this->plainSQL('SELECT `pref_items`.`id` FROM `pref_items`'), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Specify a column with alias
        $compiled = $builder->table('items')->select('name', 'n')->get();
        $this->assertEquals($this->plainSQL('SELECT `name` AS n FROM `pref_items`'), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Specify columns
        $compiled = $builder->table('items')->select(['id', 'p' => 'price'])->addSelect(['q' => 'quantity'])->get();
        $this->assertEquals($this->plainSQL('
            SELECT `id`, `price` AS p, `quantity` AS q 
            FROM `pref_items`
        '), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Specify column as a raw SQL
        $compiled = $builder->table('items')->select(new Raw('SELECT id, ? AS value FROM temp', [13]))->get();
        $this->assertEquals($this->plainSQL('
            SELECT (
                SELECT id, ? AS value
                FROM temp
            )
            FROM `pref_items`
        '), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([13], $compiled->getBindings());

        // Wipe the columns list using the select method
        $compiled = $builder->table('items')->addSelect(['quantity'])->select(['id', 'price'])->get();
        $this->assertEquals($this->plainSQL('
            SELECT `id`, `price`
            FROM `pref_items`
        '), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Incorrect from argument error
        $this->assertException(InvalidArgumentException::class, function () use ($builder) {
            $builder->builder()->from(['foo', 'bar'])->get();
        });

        // Incorrect select arguments error
        $this->assertException(InvalidArgumentException::class, function () use ($builder) {
            $builder->table('table')->select(new \stdClass())->get();
        });
        $this->assertException(InvalidArgumentException::class, function () use ($builder) {
            $builder->table('table')->select(['foo' => [1, 2, 3]])->get();
        });

        // No from error
        $this->assertException(InvalidQueryException::class, function () use ($builder) {
            $builder->builder()->select(['id', 'name'])->get();
        });
    }

    /**
     * Tests the offset and the limit features
     */
    public function testOffsetAndLimit()
    {
        $builder = new BuilderFactory();

        // Simple offset
        $compiled = $builder->table('users')->offset(15)->get();
        $this->assertEquals($this->plainSQL('SELECT * FROM `users` OFFSET ?'), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([15], $compiled->getBindings());

        // Reset offset
        $compiled = $builder->table('users')->offset(15)->offset(null)->get();
        $this->assertEquals($this->plainSQL('SELECT * FROM `users`'), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Offset with raw SQL
        $compiled = $builder->table('users')->offset(new Raw('SELECT foo FROM bar'))->get();
        $this->assertEquals($this->plainSQL('
            SELECT * 
            FROM `users`
            OFFSET (SELECT foo FROM bar)
        '), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Simple limit
        $compiled = $builder->table('users')->limit(10)->get();
        $this->assertEquals($this->plainSQL('SELECT * FROM `users` LIMIT ?'), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([10], $compiled->getBindings());

        // Reset limit
        $compiled = $builder->table('users')->limit(10)->limit(null)->get();
        $this->assertEquals($this->plainSQL('SELECT * FROM `users`'), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Limit with raw SQL
        $compiled = $builder->table('users')->limit(new Raw('SELECT foo FROM bar'))->get();
        $this->assertEquals($this->plainSQL('
            SELECT * 
            FROM `users`
            LIMIT (SELECT foo FROM bar)
        '), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([], $compiled->getBindings());

        // Both offset and limit
        $compiled = $builder->table('users')->limit(10)->offset(20)->get();
        $this->assertEquals($this->plainSQL('SELECT * FROM `users` OFFSET ? LIMIT ?'), $this->plainSQL($compiled->getSQL()));
        $this->assertEquals([20, 10], $compiled->getBindings());

        // Wrong arguments
        $this->assertException(InvalidArgumentException::class, function () use ($builder) {
            $builder->table('users')->offset('foofoobar')->get();
        });
        $this->assertException(InvalidArgumentException::class, function () use ($builder) {
            $builder->table('users')->limit('foofoobar')->get();
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
