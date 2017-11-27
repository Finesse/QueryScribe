<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\ClosureResolverInterface;
use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryProxy;
use Finesse\QueryScribe\Raw;

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
        $query = new Query();
        $superQuery = new QueryProxy($query);

        $superQuery = $superQuery
            ->from('items')
            ->where('items.size', '>', 3);

        $this->assertEquals('items', $query->table);
        $this->assertEquals('items.size', $query->where[0]->column);
        $this->assertEquals(3, $query->where[0]->value);
        $this->assertInstanceOf(Raw::class, $superQuery->raw('TEST'));

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
            protected function handleException(\Throwable $exception) {
                throw new \Exception('Sorry, error: '.$exception->getMessage());
            }
        };
        $this->assertException(\Exception::class, function () use ($superQuery) {
            $superQuery->whereIn([1, 2, 3], 'column');
        }, function (\Exception $exception) {
            $this->assertStringStartsWith('Sorry, error: ', $exception->getMessage());
        });

        // Custom handler and an error in the constructor
        $superQueryClass = get_class($superQuery);
        $baseQuery = new class extends Query {
            public function setClosureResolver(ClosureResolverInterface $closureResolver = null) {
                throw new \Exception('error 1');
            }
        };
        $this->assertException(\Exception::class, function () use ($superQueryClass, $baseQuery) {
            new $superQueryClass($baseQuery);
        }, function (\Exception $exception) {
            $this->assertEquals('Sorry, error: error 1', $exception->getMessage());
        });

        // Custom handler and errors in the makeCopy... methods
        $baseQuery = new class extends Query {
            public function makeCopyForSubQuery(): Query {
                throw new \Exception('error 1');
            }
            public function makeCopyForCriteriaGroup(): Query {
                throw new \Exception('error 2');
            }
            public function __clone() {
                throw new \Exception('error 3');
            }
        };
        $superQuery = new $superQueryClass($baseQuery); /** @var QueryProxy $superQuery */
        $this->assertException(\Exception::class, function () use ($superQuery) {
            $superQuery->resolveSubQueryClosure(function () {});
        }, function (\Exception $exception) {
            $this->assertEquals('Sorry, error: error 1', $exception->getMessage());
        });
        $this->assertException(\Exception::class, function () use ($superQuery) {
            $superQuery->resolveCriteriaGroupClosure(function () {});
        }, function (\Exception $exception) {
            $this->assertEquals('Sorry, error: error 2', $exception->getMessage());
        });
        $this->assertException(\Exception::class, function () use ($superQuery) {
            $superQuery2 = clone $superQuery;
        }, function (\Exception $exception) {
            $this->assertEquals('Sorry, error: error 3', $exception->getMessage());
        });
    }

    /**
     * Tests that closures are resolved properly
     */
    public function testClosureResolving()
    {
        // Stock resolver
        $query = new Query();
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

        $this->assertEquals('table1', $query->select[0]->table);
        $this->assertEquals('table2', $query->select[1]->table);
        $this->assertEquals('table3', $query->select[2]->table);
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
        $query1 = new Query();
        $superQuery1 = new class ($query1) extends QueryProxy {
            public function getBaseQuery(): Query {
                return parent::getBaseQuery();
            }
        };
        $superQuery1->from('table');
        $superQuery2 = clone $superQuery1;
        $query2 = $superQuery2->getBaseQuery();

        $this->assertEquals('table', $query1->table);
        $this->assertEquals('table', $query2->table);

        $superQuery1->from('goods');
        $superQuery2->from('news');

        $this->assertEquals('goods', $query1->table);
        $this->assertEquals('news', $query2->table);
    }
}
