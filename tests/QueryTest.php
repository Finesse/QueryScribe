<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criteria\ValueCriterion;
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
        $query = new Query;
        $this->assertNull($query->table);
        $this->assertNull($query->tableAlias);

        // Simple table
        $query->table('foo', 'f');
        $this->assertEquals('foo', $query->table);
        $this->assertEquals('f', $query->tableAlias);

        // Table with callback subquery
        $query->table(function (Query $query) {
            $query->addSelect('foo')->from('bar');
        });
        $this->assertEquals((new Query)->addSelect('foo')->from('bar'), $query->table);
        $this->assertNull($query->tableAlias);

        // Table with another type of callback
        $query->table(function () {
            return (new Query)->addSelect('foo2')->from('bar');
        });
        $this->assertEquals((new Query)->addSelect('foo2')->from('bar'), $query->table);;
        $this->assertNull($query->tableAlias);

        // Table with subquery
        $query->table((new Query)->table('table', 't'), 's');
        $this->assertEquals((new Query)->table('table', 't'), $query->table);;
        $this->assertEquals('s', $query->tableAlias);

        // Raw table
        $query->table(new Raw('TABLES()'));
        $this->assertEquals(new Raw('TABLES()'), $query->table);

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
        $query = new Query;
        $this->assertNull($query->getTableIdentifier());

        // Table is a subquery
        $query->table(new Query);
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
        $query = (new Query)
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

        $this->assertEquals([
            'foo' => 12345,
            'date' => new Raw('NOW()'),
            'value' => (new Query)->addAvg('height')->from('table'),
            'field' => null
        ], $query->update);

        // Incorrect column name
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->addUpdate([ 'foo' => 'bar', 'baq']);
        });

        // Incorrect value
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query)->addUpdate([ 'foo' => [1, 2, 3]]);
        });
    }

    /**
     * Tests the `setDelete` method
     */
    public function testSetDelete()
    {
        $query = new Query;
        $this->assertFalse($query->delete);

        $query->setDelete();
        $this->assertTrue($query->delete);

        // Tests that sequential setDelete call doesn't toggle the delete flag
        $query->setDelete();
        $this->assertTrue($query->delete);
    }

    /**
     * Tests the `offset` method
     */
    public function testOffset()
    {
        // No offset
        $query = new Query;
        $this->assertNull($query->offset);

        // Integer offset
        $query->offset(14);
        $this->assertEquals(14, $query->offset);

        // Callback offset
        $query->offset(function (Query $query) {
            $query->addSelect('foo')->table('bar');
        });
        $this->assertEquals((new Query)->addSelect('foo')->table('bar'), $query->offset);

        // Subquery offset
        $query->offset((new Query)->table('table'));
        $this->assertEquals((new Query)->table('table'), $query->offset);

        // Raw offset
        $query->offset(new Raw('AVG(price)'));
        $this->assertEquals(new Raw('AVG(price)'), $query->offset);

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
        $query = new Query;
        $this->assertNull($query->limit);

        // Integer limit
        $query->limit(7);
        $this->assertEquals(7, $query->limit);

        // Callback limit
        $query->limit(function (Query $query) {
            $query->addSelect('foo')->table('bar');
        });
        $this->assertEquals((new Query)->addSelect('foo')->table('bar'), $query->limit);

        // Subquery limit
        $query->limit((new Query)->table('table'));
        $this->assertEquals((new Query)->table('table'), $query->limit);

        // Raw limit
        $query->limit(new Raw('AVG(price)'));
        $this->assertEquals(new Raw('AVG(price)'), $query->limit);

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
        $query = (new Query)->table('news')->where('title', 'Interesting');
        $newQuery = $query->apply(function (Query $query) {
            $query->table('users')->where('name', 'George');
        });
        $this->assertEquals(
            (new Query)
                ->table('users')
                ->where('title', 'Interesting')
                ->where('name', 'George'),
            $newQuery
        );

        // Return a new query from the callback
        $query = (new Query)->table('news')->where('title', 'Interesting');
        $newQuery = $query->apply(function () {
            return (new Query)->table('users')->where('name', 'George');
        });
        $this->assertEquals((new Query)->table('users')->where('name', 'George'), $newQuery);

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
        $query = new Query;

        $this->assertEquals(new Raw('`column` = ?', ['orange']), $query->raw('`column` = ?', ['orange']));
    }

    /**
     * Tests that string values are not treated as callables.
     */
    public function testCallableColumnName()
    {
        $query = (new Query)
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
