<?php

namespace Finesse\QueryScribe\Tests\PostProcessors;

use Finesse\QueryScribe\PostProcessors\TablePrefixer;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\Raw;
use Finesse\QueryScribe\Tests\TestCase;

/**
 * Tests the TablePrefixPostProcessor class
 *
 * @author Surgie
 */
class TablePrefixerTest extends TestCase
{
    /**
     * Tests that a table prefix is added everywhere
     */
    public function testPrefixes()
    {
        $processor = new TablePrefixer('test_');

        $query = (new Query)
            ->addSelect('items.name')
            ->addSelect(new Raw('FOO()'))
            ->addAvg('items.value')
            ->addInsert([
                'items.name' => function (Query $query) {
                    $query->from('posts')->addCount();
                }
            ])
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
            ->inExplicitOrder('items.version', [5, (new Query)->from('versions')], true)
            ->inRandomOrder()
            ->offset(150)
            ->limit(function (Query $query) {
                $query->addCount()->from(function (Query $query) {
                    $query
                        ->from('comments')
                        ->whereRaw('NOW()');
                });
            });

        $prefixedQuery = $processor->process($query);

        $this->assertEquals(
            (new Query)
                ->addSelect('test_items.name')
                ->addSelect(new Raw('FOO()'))
                ->addAvg('test_items.value')
                ->addInsert([
                    'test_items.name' => function (Query $query) {
                        $query->from('test_posts')->addCount();
                    }
                ])
                ->addInsertFromSelect(['test_items.title'], function (Query $query) {
                    $query->from('test_users')->addSelect('test_users.name');
                })
                ->from('test_items')
                ->join('test_orders', 'test_orders.item_id', 'test_items.id')
                ->addUpdate([
                    'test_items.value' => function (Query $query) {
                        $query->from('test_products')->addAvg('test_products.price');
                    }
                ])
                ->setDelete()
                ->where('test_items.date', '>', 12121)
                ->whereBetween('test_items.position', 1, 3)
                ->orWhere(function (Query $query) {
                    $query
                        ->whereExists(function (Query $query) {
                            $query->from('test_posts')->whereColumn('test_posts.item_id', 'test_items.id');
                        })
                        ->whereIn('test_items.status', [1, 5, 89]);
                })
                ->whereNotIn('test_items.status', function (Query $query) {
                    $query
                        ->from('test_statuses')
                        ->addSelect('test_statuses.id')
                        ->whereNotNull('test_statuses.name');
                })
                ->orderBy('test_items.foo', 'desc')
                ->orderByNullLast('test_orders.comment')
                ->orderByNullFirst('test_items.review')
                ->inExplicitOrder('test_items.version', [5, (new Query)->from('test_versions')], true)
                ->inRandomOrder()
                ->offset(150)
                ->limit(function (Query $query) {
                    $query->addCount()->from(function (Query $query) {
                        $query
                            ->from('test_comments')
                            ->whereRaw('NOW()');
                    });
                }),
            $prefixedQuery
        );

        // Original query must not be modified
        $this->assertNotSame($prefixedQuery, $query);
        $this->assertNotSame($prefixedQuery->table, $query->table);
        $this->assertNotSame($prefixedQuery->insert[1], $query->insert[1]);
        $this->assertNotSame($prefixedQuery->where[3]->values->table, $query->where[3]->values->table);
    }

    /**
     * Tests that prefixes are not added when they are not needed
     */
    public function testNoPrefixes()
    {
        $processor = new TablePrefixer('test_');

        $query = (new Query)
            ->addSelect('name')
            ->addAvg('value')
            ->addInsert([
                'name' => 'Foo'
            ])
            ->addInsertFromSelect(function (Query $query) {
                $query->addSelect('height');
            })
            ->addUpdate([
                'value' => 'Bar'
            ])
            ->join(new Raw('TABLES()'), 'id', 'table_id')
            ->where('date', '>', 12121)
            ->whereBetween('position', 1, 3)
            ->orWhere(function (Query $query) {
                $query
                    ->whereExists(function (Query $query) {
                        $query->whereColumn('item_id', 'id');
                    })
                    ->whereIn('status', [1, 5, 89]);
            })
            ->whereNotIn('status', function (Query $query) {
                $query
                    ->addSelect('id')
                    ->whereNotNull('name');
            })
            ->orderBy('foo', 'desc')
            ->inExplicitOrder('version', [5, (new Query)->addSelect('number')]);

        $prefixedQuery = $processor->process($query);

        $this->assertSame($prefixedQuery, $query); // The processor should have been returned the unmodified original query
        $this->assertEquals(
            (new Query)
                ->addSelect('name')
                ->addAvg('value')
                ->addInsert([
                    'name' => 'Foo'
                ])
                ->addInsertFromSelect(function (Query $query) {
                    $query->addSelect('height');
                })
                ->addUpdate([
                    'value' => 'Bar'
                ])
                ->join(new Raw('TABLES()'), 'id', 'table_id')
                ->where('date', '>', 12121)
                ->whereBetween('position', 1, 3)
                ->orWhere(function (Query $query) {
                    $query
                        ->whereExists(function (Query $query) {
                            $query->whereColumn('item_id', 'id');
                        })
                        ->whereIn('status', [1, 5, 89]);
                })
                ->whereNotIn('status', function (Query $query) {
                    $query
                        ->addSelect('id')
                        ->whereNotNull('name');
                })
                ->orderBy('foo', 'desc')
                ->inExplicitOrder('version', [5, (new Query)->addSelect('number')]),
            $prefixedQuery
        );
    }

    /**
     * Tests that table aliases are regarded
     */
    public function testAliases()
    {
        $processor = new TablePrefixer('demo_');

        $query = (new Query)
            ->from('posts', 'p')
            ->addSelect('posts.title', 'title')
            ->addSelect('p.description', 'description')
            ->whereExists(function (Query $query) {
                $query
                    ->from('comments', 'c')
                    ->whereColumn('c.post_id', 'p.id')
                    ->where('t.date', '>', '2017-10-10') // `t` alias is in subquery
                    ->whereIn('c.type', function (Query $query) {
                        $query
                            ->from('types', 't')
                            ->whereColumn('t.name', '!=', 'c.title');
                    });
            });

        $this->assertEquals(
            (new Query)
                ->from('demo_posts', 'p')
                ->addSelect('demo_posts.title', 'title')
                ->addSelect('p.description', 'description')
                ->whereExists(function (Query $query) {
                    $query
                        ->from('demo_comments', 'c')
                        ->whereColumn('c.post_id', 'p.id')
                        ->where('t.date', '>', '2017-10-10')
                        ->whereIn('c.type', function (Query $query) {
                            $query
                                ->from('demo_types', 't')
                                ->whereColumn('t.name', '!=', 'c.title');
                        });
                }),
            $processor->process($query)
        );
    }

    /**
     * Tests the `addTablePrefix` and `addTablePrefixToColumn` methods
     */
    public function testAddTablePrefix()
    {
        $processor = new TablePrefixer('prefix_');

        $this->assertEquals('prefix_tab1', $processor->addTablePrefix('tab1'));
        $this->assertEquals('database.prefix_table', $processor->addTablePrefix('database.table'));

        $this->assertEquals('column1', $processor->addTablePrefixToColumn('column1'));
        $this->assertEquals('database.prefix_table.column1', $processor->addTablePrefixToColumn('database.table.column1'));
    }

    /**
     * Tests that the empty prefix is added correctly
     */
    public function testEmptyPrefix()
    {
        $processor = new TablePrefixer('');

        $query = (new Query)
            ->from('posts')
            ->addSelect('posts.title', 'title')
            ->whereExists(function (Query $query) {
                $query
                    ->from('comments', 'c')
                    ->whereColumn('c.post_id', 'posts.id');
            });

        $prefixedQuery = $processor->process($query);

        $this->assertSame($prefixedQuery, $query);
        $this->assertEquals(
            (new Query)
                ->from('posts')
                ->addSelect('posts.title', 'title')
                ->whereExists(function (Query $query) {
                    $query
                        ->from('comments', 'c')
                        ->whereColumn('c.post_id', 'posts.id');
                }),
            $prefixedQuery
        );
    }
}
