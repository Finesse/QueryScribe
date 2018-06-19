<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
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
        $query = new Query();
        $this->assertNull($query->table);
        $this->assertNull($query->tableAlias);

        // Simple table
        $query->table('foo', 'f');
        $this->assertAttributes(['table' => 'foo', 'tableAlias' => 'f'], $query);

        // Table with callback subquery
        $query->table(function (Query $query) {
            $query->addSelect('foo')->from('bar');
        });
        $this->assertInstanceOf(Query::class, $query->table);
        $this->assertAttributes(['table' => 'bar', 'tableAlias' => null], $query->table);
        $this->assertNull($query->tableAlias);

        // Table with another type of callback
        $query->table(function () {
            return (new Query())->addSelect('foo2')->from('bar');
        });
        $this->assertInstanceOf(Query::class, $query->table);
        $this->assertAttributes(['table' => 'bar', 'tableAlias' => null], $query->table);
        $this->assertNull($query->tableAlias);

        // Table with subquery
        $query->table((new Query())->table('table', 't'), 's');
        $this->assertInstanceOf(Query::class, $query->table);
        $this->assertAttributes(['table' => 'table', 'tableAlias' => 't'], $query->table);
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
     * Tests the `getTableIdentifier` method
     */
    public function testGetTableIdentifier()
    {
        // No table
        $query = new Query();
        $this->assertNull($query->getTableIdentifier());

        // Table is a subquery
        $query->table(new Query());
        $this->assertNull($query->getTableIdentifier());

        // Table is a string
        $query->table('news');
        $this->assertEquals('news', $query->getTableIdentifier());

        // Table has an alias
        $query->table('items', 'i');
        $this->assertEquals('i', $query->getTableIdentifier());
    }

    /**
     * Tests the `addUpdate` method
     */
    public function testAddUpdate()
    {
        $query = (new Query())
            ->addUpdate([
                'foo' => 'Bar',
                'date' => new Raw('NOW()'),
                'value' => function (Query $query) {
                    $query->addAvg('height')->from('table');
                }
            ])
            ->addUpdate([
                'foo' => 12345,
                'field' => null
            ]);

        $this->assertCount(4, $query->update);
        $this->assertEquals(12345, $query->update['foo']);
        $this->assertStatement('NOW()', [], $query->update['date']);
        $this->assertInstanceOf(Query::class, $query->update['value']);
        $this->assertEquals('table', $query->update['value']->table);
        $this->assertNull($query->update['field']);

        // Incorrect column name
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->addUpdate([ 'foo' => 'bar', 'baq']);
        });

        // Incorrect value
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->addUpdate([ 'foo' => [1, 2, 3]]);
        });
    }

    /**
     * Tests the `setDelete` method
     */
    public function testSetDelete()
    {
        $query = new Query();
        $this->assertFalse($query->delete);

        $query->setDelete();
        $this->assertTrue($query->delete);

        // Tests that sequential setDelete call doesn't toggle the delete flag
        $query->setDelete();
        $this->assertTrue($query->delete);
    }

    /**
     * Tests the ordering methods
     */
    public function testOrder()
    {
        $query = (new Query())
            ->from('post')
            ->orderBy('name')
            ->inRandomOrder()
            ->orderBy(function (Query $query) {
                $query
                    ->addAvg('price')
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
        $this->assertEquals('products', $query->order[2]->column->table);
    }

    /**
     * Tests the `offset` method
     */
    public function testOffset()
    {
        // No offset
        $query = new Query();
        $this->assertNull($query->offset);

        // Integer offset
        $query->offset(14);
        $this->assertEquals(14, $query->offset);

        // Callback offset
        $query->offset(function (Query $query) {
            $query->addSelect('foo')->table('bar');
        });
        $this->assertInstanceOf(Query::class, $query->offset);
        $this->assertEquals('bar', $query->offset->table);

        // Subquery offset
        $query->offset((new Query())->table('table'));
        $this->assertInstanceOf(Query::class, $query->offset);
        $this->assertEquals('table', $query->offset->table);

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
        $query = new Query();
        $this->assertNull($query->limit);

        // Integer limit
        $query->limit(7);
        $this->assertEquals(7, $query->limit);

        // Callback limit
        $query->limit(function (Query $query) {
            $query->addSelect('foo')->table('bar');
        });
        $this->assertInstanceOf(Query::class, $query->limit);
        $this->assertEquals('bar', $query->limit->table);

        // Subquery limit
        $query->limit((new Query())->table('table'));
        $this->assertInstanceOf(Query::class, $query->limit);
        $this->assertEquals('table', $query->limit->table);

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
     * Tests the `apply` method
     */
    public function testApply()
    {
        // Modify the given query in the callback
        $query = (new Query())->table('news')->where('title', 'Interesting');
        $newQuery = $query->apply(function (Query $query) {
            $query->table('users')->where('name', 'George');
        });
        $this->assertAttributeEquals('users', 'table', $newQuery);
        $this->assertAttributeCount(2, 'where', $newQuery);
        $this->assertAttributes(['column' => 'title', 'value' => 'Interesting'], $newQuery->where[0]);
        $this->assertAttributes(['column' => 'name', 'value' => 'George'], $newQuery->where[1]);

        // Return a new query from the callback
        $query = (new Query())->table('news')->where('title', 'Interesting');
        $newQuery = $query->apply(function () {
            return (new Query())->table('users')->where('name', 'George');
        });
        $this->assertAttributeEquals('users', 'table', $newQuery);
        $this->assertAttributeCount(1, 'where', $newQuery);
        $this->assertAttributes(['column' => 'name', 'value' => 'George'], $newQuery->where[0]);

        // Wrong return value
        $this->assertException(InvalidReturnValueException::class, function () use ($query) {
            $query->apply(function () {
                return 'Hello';
            });
        });
    }

    /**
     * Tests that the other trait methods are available
     */
    public function testTraits()
    {
        $query = new Query();

        $raw = $query->raw('`column` = ?', ['orange']);
        $this->assertInstanceOf(Raw::class, $raw);
        $this->assertStatement('`column` = ?', ['orange'], $raw);
    }

    /**
     * Tests that string values are not treated as callables.
     */
    public function testCallableColumnName()
    {
        $query = (new Query())
            ->table('date')
            ->addSelect('is_array')
            ->where('sprintf', 'ucfirst');

        $this->assertAttributes(['table' => 'date', 'select' => ['is_array']], $query);
        $this->assertAttributes(['column' => 'sprintf', 'value' => 'ucfirst'], $query->where[0]);
    }

    /**
     * Tests that exceptions are passed throw the `handleException` method
     */
    public function testHandleException()
    {
        $query = new class extends Query {
            protected function handleException(\Throwable $exception)
            {
                throw new \TypeError('Test: '.$exception->getMessage(), $exception->getCode(), $exception);
            }
        };

        $this->assertException(\TypeError::class, function () use ($query) {
            $query->from(['foo', 'bar']);
        }, function (\TypeError $exception) {
            $this->assertStringStartsWith('Test: ', $exception->getMessage());
            $this->assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
        });
    }
}
