<?php

namespace Finesse\QueryScribe\Tests\PostProcessors;

use Finesse\QueryScribe\PostProcessors\ExplicitTables;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\Raw;
use Finesse\QueryScribe\Tests\TestCase;

/**
 * Tests the ExplicitTables class
 *
 * @author Surgie
 */
class ExplicitTablesTest extends TestCase
{
    /**
     * Tests that a table identifier is added everywhere
     */
    public function testAddition()
    {
        $processor = new ExplicitTables();

        $query = (new Query)
            ->addSelect('name')
            ->addSelect(new Raw('FOO()'))
            ->addAvg('value')
            ->addInsertFromSelect(['title'], function (Query $query) {
                $query->from('users')->addSelect('name');
            })
            ->from('items')
            ->join('orders', 'orders.item_id', 'id')
            ->addUpdate([
                'value' => function (Query $query) {
                    $query->from('products')->addAvg('price');
                }
            ])
            ->setDelete()
            ->where('date', '>', 12121)
            ->whereBetween('position', 1, 3)
            ->orWhere(function (Query $query) {
                $query
                    ->whereExists(function (Query $query) {
                        $query->from('posts')->whereColumn('item_id', 'items.id');
                    })
                    ->whereIn('status', [1, 5, 89]);
            })
            ->whereNotIn('status', function (Query $query) {
                $query
                    ->from('statuses')
                    ->addSelect('id')
                    ->whereNotNull('name');
            })
            ->orderBy('foo', 'desc')
            ->orderByNullLast('orders.comment')
            ->orderByNullFirst('review')
            ->inRandomOrder()
            ->offset(150);

        $explicitQuery = $processor->process($query);

        $this->assertEquals(
            (new Query)
                ->addSelect('items.name')
                ->addSelect(new Raw('FOO()'))
                ->addAvg('items.value')
                ->addInsertFromSelect(['items.title'], function (Query $query) {
                    $query->from('users')->addSelect('users.name');
                })
                ->from('items')
                ->join('orders', 'orders.item_id', 'items.id')
                ->addUpdate([
                    'items.value' => function (Query $query) {
                        $query->from('products')->addAvg('products.price');
                    }
                ])
                ->setDelete()
                ->where('items.date', '>', 12121)
                ->whereBetween('items.position', 1, 3)
                ->orWhere(function (Query $query) {
                    $query
                        ->whereExists(function (Query $query) {
                            $query->from('posts')->whereColumn('posts.item_id', 'items.id');
                        })
                        ->whereIn('items.status', [1, 5, 89]);
                })
                ->whereNotIn('items.status', function (Query $query) {
                    $query
                        ->from('statuses')
                        ->addSelect('statuses.id')
                        ->whereNotNull('statuses.name');
                })
                ->orderBy('items.foo', 'desc')
                ->orderByNullLast('orders.comment')
                ->orderByNullFirst('items.review')
                ->inRandomOrder()
                ->offset(150),
            $explicitQuery
        );

        // Original query must not be modified
        $this->assertNotSame($explicitQuery, $query);
        $this->assertNotSame($explicitQuery->insert[0], $query->insert[0]);
        $this->assertNotSame($explicitQuery->where[3]->values, $query->where[3]->values);
    }

    /**
     * Tests that table names are not added when they are already added
     */
    public function testAdditionNotRequired()
    {
        $processor = new ExplicitTables();

        $query = (new Query)
            ->table('users')
            ->addSelect('users.name')
            ->addAvg('users.value')
            ->addInsert([
                'users.name' => 'Foo'
            ])
            ->addInsertFromSelect(function (Query $query) {
                $query->addSelect('products.height')->from('products');
            })
            ->addUpdate([
                'users.value' => 'Bar'
            ])
            ->where('users.date', '>', 12121)
            ->whereBetween('users.position', 1, 3)
            ->orWhere(function (Query $query) {
                $query
                    ->whereExists(function (Query $query) {
                        $query->whereColumn('item_id', 'id');
                    })
                    ->whereIn('users.status', [1, 5, 89]);
            })
            ->whereNotIn('users.status', function (Query $query) {
                $query
                    ->addSelect('id')
                    ->whereNotNull('name');
            })
            ->orderBy('users.foo', 'desc')
            ->inExplicitOrder('users.version', [5, 3, 6]);

        $explicitQuery = $processor->process($query);

        $this->assertSame($explicitQuery, $query); // The processor should have been returned the unmodified original query
        $this->assertEquals(
            (new Query)
                ->table('users')
                ->addSelect('users.name')
                ->addAvg('users.value')
                ->addInsert([
                    'users.name' => 'Foo'
                ])
                ->addInsertFromSelect(function (Query $query) {
                    $query->addSelect('products.height')->from('products');
                })
                ->addUpdate([
                    'users.value' => 'Bar'
                ])
                ->where('users.date', '>', 12121)
                ->whereBetween('users.position', 1, 3)
                ->orWhere(function (Query $query) {
                    $query
                        ->whereExists(function (Query $query) {
                            $query->whereColumn('item_id', 'id');
                        })
                        ->whereIn('users.status', [1, 5, 89]);
                })
                ->whereNotIn('users.status', function (Query $query) {
                    $query
                        ->addSelect('id')
                        ->whereNotNull('name');
                })
                ->orderBy('users.foo', 'desc')
                ->inExplicitOrder('users.version', [5, 3, 6]),
            $explicitQuery
        );
    }

    /**
     * Tests that table aliases are used
     */
    public function testAliases()
    {
        $processor = new ExplicitTables();

        $query = (new Query)
            ->from('posts', 'p')
            ->addSelect('title', 'title')
            ->addSelect('description', 'description')
            ->join(['users', 'u'], 'u.id', 'author_id')
            ->whereExists(function (Query $query) {
                $query
                    ->from('comments') // No alias
                    ->whereColumn('post_id', 'p.id')
                    ->whereIn('type', function (Query $query) {
                        $query
                            ->from('types', 't')
                            ->whereColumn('name', '!=', 'comments.title');
                    });
            });

        $this->assertEquals(
            (new Query)
                ->from('posts', 'p')
                ->addSelect('p.title', 'title')
                ->addSelect('p.description', 'description')
                ->join(['users', 'u'], 'u.id', 'p.author_id')
                ->whereExists(function (Query $query) {
                    $query
                        ->from('comments')
                        ->whereColumn('comments.post_id', 'p.id')
                        ->whereIn('comments.type', function (Query $query) {
                            $query
                                ->from('types', 't')
                                ->whereColumn('t.name', '!=', 'comments.title');
                        });
                }),
            $processor->process($query)
        );
    }

    /**
     * Tests processing though invoking the object as a function
     */
    public function testInvoke()
    {
        $processor = new ExplicitTables();

        $query = (new Query)
            ->addSelect('name')
            ->from('items')
            ->where('date', '>', 12121);

        $explicitQuery = $query->apply($processor);

        $this->assertEquals(
            (new Query)
                ->addSelect('items.name')
                ->from('items')
                ->where('items.date', '>', 12121),
            $explicitQuery
        );

        // Original query must not be modified
        $this->assertNotSame($explicitQuery, $query);
        $this->assertNotSame($explicitQuery->where[0], $query->where[0]);
    }
}
