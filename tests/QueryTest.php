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
     * Tests the `from` method
     */
    public function testFrom()
    {
        // No from
        $query = new Query('pref_');
        $this->assertNull($query->from);
        $this->assertNull($query->fromAlias);

        // Simple from
        $query->from('foo', 'f');
        $this->assertAttributes(['from' => 'pref_foo', 'fromAlias' => 'f'], $query);

        // From with callback subquery
        $query->from(function (Query $query) {
            $query->select('foo')->from('bar');
        });
        $this->assertInstanceOf(Query::class, $query->from);
        $this->assertAttributes(['from' => 'pref_bar', 'fromAlias' => null], $query->from);
        $this->assertNull($query->fromAlias);

        // From with another type of callback
        $query->from(function () {
            return (new Query('test_'))->select('foo2')->from('bar');
        });
        $this->assertInstanceOf(Query::class, $query->from);
        $this->assertAttributes(['from' => 'test_bar', 'fromAlias' => null], $query->from);
        $this->assertNull($query->fromAlias);

        // From with subquery
        $query->from((new Query('sub_'))->from('table', 't'), 's');
        $this->assertInstanceOf(Query::class, $query->from);
        $this->assertAttributes(['from' => 'sub_table', 'fromAlias' => 't'], $query->from);
        $this->assertEquals('s', $query->fromAlias);

        // Raw from
        $query->from(new Raw('TABLES()'));
        $this->assertInstanceOf(StatementInterface::class, $query->from);
        $this->assertEquals('TABLES()', $query->from->getSQL());
        $this->assertEquals([], $query->from->getBindings());

        // Wrong argument
        $this->assertException(InvalidArgumentException::class, function () use ($query) {
            $query->from(['foo', 'bar']);
        });
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
                    ->from('products')
                    ->whereColumn('post.category_id', 'price.category_id');
            }, 'desc');

        $this->assertCount(3, $query->order);
        $this->assertInstanceOf(Order::class, $query->order[0]);
        $this->assertAttributes(['column' => 'name', 'isDescending' => false], $query->order[0]);
        $this->assertEquals('random', $query->order[1]);
        $this->assertInstanceOf(Order::class, $query->order[2]);
        $this->assertEquals(true, $query->order[2]->isDescending);
        $this->assertInstanceOf(Query::class, $query->order[2]->column);
        $this->assertEquals('pref_products', $query->order[2]->column->from);
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
            $query->select('foo')->from('bar');
        });
        $this->assertInstanceOf(Query::class, $query->offset);
        $this->assertEquals('pref_bar', $query->offset->from);

        // Subquery offset
        $query->offset((new Query('sub_'))->from('table'));
        $this->assertInstanceOf(Query::class, $query->offset);
        $this->assertEquals('sub_table', $query->offset->from);

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
            $query->select('foo')->from('bar');
        });
        $this->assertInstanceOf(Query::class, $query->limit);
        $this->assertEquals('pref_bar', $query->limit->from);

        // Subquery limit
        $query->limit((new Query('sub_'))->from('table'));
        $this->assertInstanceOf(Query::class, $query->limit);
        $this->assertEquals('sub_table', $query->limit->from);

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
            ->from('date')
            ->select('is_array')
            ->where('sprintf', 'ucfirst');

        $this->assertAttributes(['from' => 'pref_date', 'select' => ['is_array']], $query);
        $this->assertAttributes(['column' => 'sprintf', 'value' => 'ucfirst'], $query->where[0]);
    }
}
