<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\ClosureResolverInterface;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\Query;

/**
 * Tests the ResolvesClosuresTrait trait
 *
 * @author Surgie
 */
class ResolvesClosuresTraitTest extends TestCase
{
    /**
     * Tests that closure subquery return values are treated right
     */
    public function testClosureResolve()
    {
        $query = (new Query('prefix_'))
            ->select([
                function (Query $query) {
                    $query->from('table1');
                },
                function (Query $query) {
                    return $query->from('table2');
                },
                function () {
                    return (new Query())->from('table3');
                }
            ])
            ->where(function (Query $query) {
                $query->where('table1.column1', 1);
            })
            ->where(function (Query $query) {
                return $query->where('table2.column2', 1);
            })
            ->where(function () {
                return (new Query())->where('table2.column2', 1);
            });

        $this->assertEquals('prefix_table1', $query->select[0]->table);
        $this->assertEquals('prefix_table2', $query->select[1]->table);
        $this->assertEquals('table3', $query->select[2]->table);

        $this->assertException(InvalidReturnValueException::class, function () {
            (new Query())->select(function () {
                return 'Big bang';
            });
        });
        $this->assertException(InvalidReturnValueException::class, function () {
            (new Query())->where(function () {
                return 'Big bang';
            });
        });
    }

    /**
     * Tests that closure resolving works with a custom resolver
     */
    public function testCustomClosureResolver()
    {
        $query = (new Query('prefix_'))
            ->setClosureResolver(new class implements ClosureResolverInterface {
                public function resolveSubQueryClosure(\Closure $callback): Query
                {
                    return (new Query())->from('I am for subquery');
                }
                public function resolveCriteriaGroupClosure(\Closure $callback): Query
                {
                    return (new Query())->where('I am for criteria group', 0);
                }
            })
            ->select(function () {})
            ->where(function () {});

        $this->assertEquals('I am for subquery', $query->select[0]->table);
        $this->assertEquals('I am for criteria group', $query->where[0]->criteria[0]->column);
    }
}
