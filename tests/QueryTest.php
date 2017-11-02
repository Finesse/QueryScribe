<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Order;
use Finesse\QueryScribe\Raw;
use Finesse\QueryScribe\StatementInterface;

/**
 * Tests the Query class
 *
 * @author Surgie
 */
class QueryTest extends TestCase
{
    /**
     * Tests the `table` method
     */
    public function testTable()
    {
        // No table
        $query = new Query('pref_');
        $this->assertNull($query->table);
        $this->assertNull($query->tableAlias);

        // Simple table
        $query->table('foo', 'f');
        $this->assertAttributes(['table' => 'pref_foo', 'tableAlias' => 'f'], $query);

        // Table with callback subquery
        $query->table(function (Query $query) {
            $query->select('foo')->from('bar');
        });
        $this->assertInstanceOf(Query::class, $query->table);
        $this->assertAttributes(['table' => 'pref_bar', 'tableAlias' => null], $query->table);
        $this->assertNull($query->tableAlias);

        // Table with another type of callback
        $query->table(function () {
            return (new Query('test_'))->select('foo2')->from('bar');
        });
        $this->assertInstanceOf(Query::class, $query->table);
        $this->assertAttributes(['table' => 'test_bar', 'tableAlias' => null], $query->table);
        $this->assertNull($query->tableAlias);

        // Table with subquery
        $query->table((new Query('sub_'))->table('table', 't'), 's');
        $this->assertInstanceOf(Query::class, $query->table);
        $this->assertAttributes(['table' => 'sub_table', 'tableAlias' => 't'], $query->table);
        $this->assertEquals('s', $query->tableAlias);

        // Raw table
        $query->table(new Raw('TABLES()'));
        $this->assertStatement('TABLES()', [], $query->table);

        // Wrong argument
        $this->assertException(InvalidArgumentException::class, function () use ($query) {
            $query->table(['foo', 'bar']);
        });
    }

    /**
     * Tests the `update` method
     */
    public function testUpdate()
    {
        $query = (new Query('pref_'))
            ->update([
                'foo' => 'Bar',
                'date' => new Raw('NOW()'),
                'value' => function (Query $query) {
                    $query->avg('height')->from('table');
                }
            ])
            ->update([
                'foo' => 12345,
                'field' => null
            ]);

        $this->assertCount(4, $query->update);
        $this->assertEquals(12345, $query->update['foo']);
        $this->assertStatement('NOW()', [], $query->update['date']);
        $this->assertInstanceOf(Query::class, $query->update['value']);
        $this->assertEquals('pref_table', $query->update['value']->table);
        $this->assertNull($query->update['field']);

        // Incorrect column name
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->update(['foo' => 'bar', 'baq']);
        });

        // Incorrect value
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->update(['foo' => [1, 2, 3]]);
        });
    }

    /**
     * Tests the `delete` method
     */
    public function testDelete()
    {
        $query = new Query();
        $this->assertFalse($query->delete);

        $query->delete();
        $this->assertTrue($query->delete);

        // Tests that sequential delete call doesn't toggle the delete flag
        $query->delete();
        $this->assertTrue($query->delete);
    }

    /**
     * Tests the ordering methods
     */
    public function testOrder()
    {
        $query = (new Query('pref_'))
            ->from('post')
            ->orderBy('name')
            ->inRandomOrder()
            ->orderBy(function (Query $query) {
                $query
                    ->avg('price')
                    ->table('products')
                    ->whereColumn('post.category_id', 'price.category_id');
            }, 'desc');

        $this->assertCount(3, $query->order);
        $this->assertInstanceOf(Order::class, $query->order[0]);
        $this->assertAttributes(['column' => 'name', 'isDescending' => false], $query->order[0]);
        $this->assertEquals('random', $query->order[1]);
        $this->assertInstanceOf(Order::class, $query->order[2]);
        $this->assertEquals(true, $query->order[2]->isDescending);
        $this->assertInstanceOf(Query::class, $query->order[2]->column);
        $this->assertEquals('pref_products', $query->order[2]->column->table);
    }

    /**
     * Tests the `offset` method
     */
    public function testOffset()
    {
        // No offset
        $query = new Query('pref_');
        $this->assertNull($query->offset);

        // Integer offset
        $query->offset(14);
        $this->assertEquals(14, $query->offset);

        // Callback offset
        $query->offset(function (Query $query) {
            $query->select('foo')->table('bar');
        });
        $this->assertInstanceOf(Query::class, $query->offset);
        $this->assertEquals('pref_bar', $query->offset->table);

        // Subquery offset
        $query->offset((new Query('sub_'))->table('table'));
        $this->assertInstanceOf(Query::class, $query->offset);
        $this->assertEquals('sub_table', $query->offset->table);

        // Raw offset
        $query->offset(new Raw('AVG(price)'));
        $this->assertInstanceOf(StatementInterface::class, $query->offset);
        $this->assertEquals('AVG(price)', $query->offset->getSQL());

        // Wrong argument
        $this->assertException(InvalidArgumentException::class, function () use ($query) {
            $query->offset(['foo', 'bar']);
        });

        // How is limit doing?
        $this->assertNull($query->limit);
    }

    /**
     * Tests the `limit` method
     */
    public function testLimit()
    {
        // No limit
        $query = new Query('pref_');
        $this->assertNull($query->limit);

        // Integer limit
        $query->limit(7);
        $this->assertEquals(7, $query->limit);

        // Callback limit
        $query->limit(function (Query $query) {
            $query->select('foo')->table('bar');
        });
        $this->assertInstanceOf(Query::class, $query->limit);
        $this->assertEquals('pref_bar', $query->limit->table);

        // Subquery limit
        $query->limit((new Query('sub_'))->table('table'));
        $this->assertInstanceOf(Query::class, $query->limit);
        $this->assertEquals('sub_table', $query->limit->table);

        // Raw limit
        $query->limit(new Raw('AVG(price)'));
        $this->assertInstanceOf(StatementInterface::class, $query->limit);
        $this->assertEquals('AVG(price)', $query->limit->getSQL());

        // Wrong argument
        $this->assertException(InvalidArgumentException::class, function () use ($query) {
            $query->limit(['foo', 'bar']);
        });

        // How is offset doing?
        $this->assertNull($query->offset);
    }

    /**
     * Tests that the other trait methods are available
     */
    public function testTraits()
    {
        $query = new Query('prefix_');

        $this->assertEquals('prefix_table', $query->addTablePrefix('table'));

        $raw = $query->raw('`column` = ?', ['orange']);
        $this->assertInstanceOf(Raw::class, $raw);
        $this->assertStatement('`column` = ?', ['orange'], $raw);
    }

    /**
     * Tests that string values are not treated as callables.
     */
    public function testCallableColumnName()
    {
        $query = (new Query('pref_'))
            ->table('date')
            ->select('is_array')
            ->where('sprintf', 'ucfirst');

        $this->assertAttributes(['table' => 'pref_date', 'select' => ['is_array']], $query);
        $this->assertAttributes(['column' => 'sprintf', 'value' => 'ucfirst'], $query->where[0]);
    }
}
