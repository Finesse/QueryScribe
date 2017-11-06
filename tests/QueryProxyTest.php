<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryProxy;

/**
 * Tests the QueryProxy class
 *
 * @author Surgie
 */
class QueryProxyTest extends TestCase
{
    /**
     * Tests that the Query methods are proxied
     */
    public function testMethodProxying()
    {
        $query = new Query('prefix');
        $superQuery = new QueryProxy($query);

        $superQuery = $superQuery
            ->from('items')
            ->where('items.size', '>', 3);

        $this->assertAttributes(['table' => 'prefixitems', 'tableAlias' => 'items'], $query);
        $this->assertEquals('items.size', $query->where[0]->column);
        $this->assertEquals(3, $query->where[0]->value);
        $this->assertEquals('prefixfoo', $superQuery->addTablePrefix('foo'));

        // Checks that a QueryProxy instance is returned
        $this->assertInstanceOf(QueryProxy::class, $superQuery);

        // Checks that the forbidden methods are not called
        $this->assertException(\Error::class, function () use ($superQuery) {
            $superQuery->makeCopyForSubQuery();
        }, function (\Error $error) {
            $this->assertEquals(
                'Call to undefined method Finesse\QueryScribe\QueryProxy::makeCopyForSubQuery()',
                $error->getMessage()
            );
        });
    }

    /**
     * Tests that the base query exceptions are treated right
     */
    public function testExceptionsHandling()
    {
        $query = new Query();

        // Stock handler
        $superQuery = new QueryProxy($query);
        $this->assertException(InvalidArgumentException::class, function () use ($superQuery) {
            $superQuery->whereExists(['column1', 'column2']);
        });

        // Custom handler
        $superQuery = new class ($query) extends QueryProxy {
            protected function handleBaseQueryException(\Throwable $exception) {
                return 'Sorry, error: '.get_class($exception);
            }
        };
        $this->assertEquals(
            'Sorry, error: '.InvalidArgumentException::class,
            $superQuery->whereIn([1, 2, 3], 'column')
        );
    }

    /**
     * Tests that closures are resolved properly
     */
    public function testClosureResolving()
    {
        // Stock resolver
        $query = new Query('prefix_');
        $superQuery = new QueryProxy($query);
        $superQuery
            ->addSelect(function ($query) {
                $this->assertInstanceOf(QueryProxy::class, $query);
                $query->from('table1');
            })
            ->addSelect(function () {
                return (new QueryProxy(new Query()))->from('table2');
            })
            ->addSelect(function () {
                return (new Query())->from('table3');
            })
            ->where(function ($query) {
                $this->assertInstanceOf(QueryProxy::class, $query);
                $query->where('table4.column', 'foo');
            });

        $this->assertAttributes(['table' => 'prefix_table1', 'tableAlias' => 'table1'], $query->select[0]);
        $this->assertAttributes(['table' => 'table2', 'tableAlias' => null], $query->select[1]);
        $this->assertAttributes(['table' => 'table3', 'tableAlias' => null], $query->select[2]);
        $this->assertEquals('table4.column', $query->where[0]->criteria[0]->column);

        $this->assertException(InvalidReturnValueException::class, function () use ($superQuery) {
            $superQuery->from(function () {
                return 'table';
            });
        });

        // Custom resolver
        $query = new Query();
        $superQuery = new class ($query) extends QueryProxy {
            public function resolveSubQueryClosure(\Closure $callback): Query
            {
                return (new Query())->from('I am for subquery');
            }
            public function resolveCriteriaGroupClosure(\Closure $callback): Query
            {
                return (new Query())->where('I am for criteria group', 0);
            }
        };
        $superQuery->addSelect(function () {})->where(function () {});

        $this->assertEquals('I am for subquery', $query->select[0]->table);
        $this->assertEquals('I am for criteria group', $query->where[0]->criteria[0]->column);
    }

    /**
     * Tests cloning
     */
    public function testClone()
    {
        $query1 = new Query('prefix_');
        $superQuery1 = new class ($query1) extends QueryProxy {
            public function getBaseQuery(): Query {
                return parent::getBaseQuery();
            }
        };
        $superQuery1->from('table');
        $superQuery2 = clone $superQuery1;
        $query2 = $superQuery2->getBaseQuery();

        $this->assertEquals('prefix_table', $query1->table);
        $this->assertEquals('prefix_table', $query2->table);

        $superQuery1->from('goods');
        $superQuery2->from('news');

        $this->assertEquals('prefix_goods', $query1->table);
        $this->assertEquals('prefix_news', $query2->table);
    }
}
