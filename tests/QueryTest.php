<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Query;
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
     * Tests the from method
     */
    public function testFrom()
    {
        // No from
        $query = new Query('pref_');
        $this->assertNull($query->from);
        $this->assertNull($query->fromAlias);

        // Simple from
        $query->from('foo', 'f');
        $this->assertEquals('pref_foo', $query->from);
        $this->assertEquals('f', $query->fromAlias);

        // From with callback subquery
        $query->from(function (Query $query) {
            $query->select('foo')->from('bar');
        });
        $this->assertInstanceOf(Query::class, $query->from);
        $this->assertEquals('pref_bar', $query->from->from);
        $this->assertNull($query->from->fromAlias);
        $this->assertNull($query->fromAlias);

        // From with another type of callback
        $query->from(function () {
            return (new Query('test_'))->select('foo2')->from('bar');
        });
        $this->assertInstanceOf(Query::class, $query->from);
        $this->assertEquals('test_bar', $query->from->from);
        $this->assertNull($query->from->fromAlias);
        $this->assertNull($query->fromAlias);

        // From with subquery
        $query->from((new Query('sub_'))->from('table', 't'), 's');
        $this->assertInstanceOf(Query::class, $query->from);
        $this->assertEquals('sub_table', $query->from->from);
        $this->assertEquals('t', $query->from->fromAlias);
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
     * Tests the select method
     */
    public function testSelect()
    {
        // No select
        $query = (new Query('pref_'));
        $this->assertEquals([], $query->select);

        // One column
        $query = (new Query('pref_'))->select('name', 'n');
        $this->assertEquals(['n' => 'name'], $query->select);

        // Many columns with different cases
        $query = (new Query('pref_'))->select([
            'value',
            't' => 'table.title',
            function (Query $query) {
                $query->select('foo')->from('bar');
            },
            (new Query('pref2_'))->select('foo')->from('bar'),
            'price' => new Raw('AVG(price) + ?', [14])
        ]);
        $this->assertCount(5, $query->select);
        $this->assertEquals('value', $query->select[0]);
        $this->assertEquals('pref_table.title', $query->select['t']);
        $this->assertInstanceOf(Query::class, $query->select[1]);
        $this->assertEquals('pref_bar', $query->select[1]->from);
        $this->assertEquals(['foo'], $query->select[1]->select);
        $this->assertInstanceOf(Query::class, $query->select[2]);
        $this->assertEquals('pref2_bar', $query->select[2]->from);
        $this->assertEquals(['foo'], $query->select[2]->select);
        $this->assertInstanceOf(StatementInterface::class, $query->select['price']);
        $this->assertEquals('AVG(price) + ?', $query->select['price']->getSQL());
        $this->assertEquals([14], $query->select['price']->getBindings());

        // Wrong argument
        $this->assertException(InvalidArgumentException::class, function () {
            (new Query())->select([
                'value',
                ['column', 'alias']
            ]);
        });
    }

    /**
     * Tests the offset method
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
     * Tests the offset method
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
     * Tests the makeEmptyCopy method
     */
    public function testMakeEmptyCopy()
    {
        $query = (new Query('test_'))
            ->from('table')
            ->select('column')
            ->offset(150)
            ->limit(10);

        $emptyQuery = $query->makeEmptyCopy();
        $this->assertInstanceOf(Query::class, $emptyQuery);
        foreach (get_object_vars($query) as $property => $value) {
            if ($property === 'select') {
                $this->assertEquals([], $emptyQuery->$property);
            } else {
                $this->assertNull($emptyQuery->$property);
            }
        }
    }

    /**
     * Tests that the trait methods are available
     */
    public function testTraits()
    {
        $query = new Query('prefix_');

        $this->assertEquals('prefix_table', $query->addTablePrefix('table'));

        $raw = $query->raw('`column` = ?', ['orange']);
        $this->assertInstanceOf(Raw::class, $raw);
        $this->assertEquals($this->plainSQL('`column` = ?'), $this->plainSQL($raw->getSQL()));
        $this->assertEquals(['orange'], $raw->getBindings());
    }
}
