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

        $query = (new Query())
            ->table(function (Query $query) {
                $query
                    ->from('items')
                    ->whereRaw('NOW()');
            })
            ->addSelect('database.items.name')
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
            ->inRandomOrder()
            ->offset(150)
            ->limit(function (Query $query) {
                $query->addCount()->from('comments');
            });

        $prefixedQuery = $processor->process($query);

        $this->assertEquals('test_items', $prefixedQuery->table->table);
        $this->assertStatement('NOW()', [], $prefixedQuery->table->where[0]->raw);
        $this->assertCount(3, $prefixedQuery->select);
        $this->assertEquals('database.test_items.name', $prefixedQuery->select[0]);
        $this->assertStatement('FOO()', [], $prefixedQuery->select[1]);
        $this->assertEquals('test_items.value', $prefixedQuery->select[2]->column);
        $this->assertCount(2, $prefixedQuery->insert);
        $this->assertEquals(['test_items.name'], array_keys($prefixedQuery->insert[0]));
        $this->assertEquals('test_posts', $prefixedQuery->insert[0]['test_items.name']->table);
        $this->assertEquals(['test_items.title'], $prefixedQuery->insert[1]->columns);
        $this->assertEquals('test_users', $prefixedQuery->insert[1]->selectQuery->table);
        $this->assertEquals('test_users.name', $prefixedQuery->insert[1]->selectQuery->select[0]);
        $this->assertEquals(['test_items.value'], array_keys($prefixedQuery->update));
        $this->assertEquals('test_products', $prefixedQuery->update['test_items.value']->table);
        $this->assertEquals('test_products.price', $prefixedQuery->update['test_items.value']->select[0]->column);
        $this->assertTrue($prefixedQuery->delete);
        $this->assertCount(4, $prefixedQuery->where);
        $this->assertAttributes(['column' => 'test_items.date', 'value' => 12121], $prefixedQuery->where[0]);
        $this->assertAttributes(['column' => 'test_items.position', 'min' => 1, 'max' => 3], $prefixedQuery->where[1]);
        $this->assertCount(2, $prefixedQuery->where[2]->criteria);
        $this->assertEquals('test_posts', $prefixedQuery->where[2]->criteria[0]->subQuery->table);
        $this->assertAttributes(['column1' => 'test_posts.item_id', 'column2' => 'test_items.id'], $prefixedQuery->where[2]->criteria[0]->subQuery->where[0]);
        $this->assertAttributes(['column' => 'test_items.status', 'values' => [1, 5, 89]], $prefixedQuery->where[2]->criteria[1]);
        $this->assertEquals('test_items.status', $prefixedQuery->where[3]->column);
        $this->assertEquals('test_statuses', $prefixedQuery->where[3]->values->table);
        $this->assertEquals('test_statuses.id', $prefixedQuery->where[3]->values->select[0]);
        $this->assertEquals('test_statuses.name', $prefixedQuery->where[3]->values->where[0]->column);
        $this->assertCount(2, $prefixedQuery->order);
        $this->assertEquals('test_items.foo', $prefixedQuery->order[0]->column);
        $this->assertEquals('random', $prefixedQuery->order[1]);
        $this->assertEquals(150, $prefixedQuery->offset);
        $this->assertEquals('test_comments', $prefixedQuery->limit->table);

        // Original query must not be modified
        $this->assertNotEquals($prefixedQuery, $query);
        $this->assertNotEquals($prefixedQuery->table->table, $query->table->table);
        $this->assertNotEquals($prefixedQuery->insert[1], $query->insert[1]);
        $this->assertNotEquals($prefixedQuery->where[3]->values->table, $query->where[3]->values->table);
    }

    /**
     * Tests that prefixes are not added when they are not needed
     */
    public function testNoPrefixes()
    {
        $processor = new TablePrefixer('test_');

        $query = (new Query())
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
            ->orderBy('foo', 'desc');

        $prefixedQuery = $processor->process($query);

        $this->assertCount(2, $prefixedQuery->select);
        $this->assertEquals('name', $prefixedQuery->select[0]);
        $this->assertEquals('value', $prefixedQuery->select[1]->column);
        $this->assertCount(2, $prefixedQuery->insert);
        $this->assertEquals(['name' => 'Foo'], $prefixedQuery->insert[0]);
        $this->assertNull($prefixedQuery->insert[1]->columns);
        $this->assertEquals('height', $prefixedQuery->insert[1]->selectQuery->select[0]);
        $this->assertEquals(['value' => 'Bar'], $prefixedQuery->update);
        $this->assertCount(4, $prefixedQuery->where);
        $this->assertAttributes(['column' => 'date', 'value' => 12121], $prefixedQuery->where[0]);
        $this->assertAttributes(['column' => 'position', 'min' => 1, 'max' => 3], $prefixedQuery->where[1]);
        $this->assertCount(2, $prefixedQuery->where[2]->criteria);
        $this->assertAttributes(['column1' => 'item_id', 'column2' => 'id'], $prefixedQuery->where[2]->criteria[0]->subQuery->where[0]);
        $this->assertAttributes(['column' => 'status', 'values' => [1, 5, 89]], $prefixedQuery->where[2]->criteria[1]);
        $this->assertEquals('status', $prefixedQuery->where[3]->column);
        $this->assertEquals('id', $prefixedQuery->where[3]->values->select[0]);
        $this->assertEquals('name', $prefixedQuery->where[3]->values->where[0]->column);
        $this->assertCount(1, $prefixedQuery->order);
        $this->assertEquals('foo', $prefixedQuery->order[0]->column);
        $this->assertEquals($prefixedQuery, $query); // The processor should have been returned the unmodified original query
    }

    /**
     * Tests that table aliases are regarded
     */
    public function testAliases()
    {
        $processor = new TablePrefixer('demo_');

        $query = (new Query())
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

        $prefixedQuery = $processor->process($query);

        $this->assertEquals('demo_posts', $prefixedQuery->table);
        $this->assertEquals('p', $prefixedQuery->tableAlias);
        $this->assertEquals(['title' => 'demo_posts.title', 'description' => 'p.description'], $prefixedQuery->select);
        $this->assertEquals('demo_comments', $prefixedQuery->where[0]->subQuery->table);
        $this->assertEquals('c', $prefixedQuery->where[0]->subQuery->tableAlias);
        $this->assertAttributes(['column1' => 'c.post_id', 'column2' => 'p.id'], $prefixedQuery->where[0]->subQuery->where[0]);
        $this->assertEquals('demo_t.date', $prefixedQuery->where[0]->subQuery->where[1]->column);
        $this->assertEquals('c.type', $prefixedQuery->where[0]->subQuery->where[2]->column);
        $this->assertEquals('demo_types', $prefixedQuery->where[0]->subQuery->where[2]->values->table);
        $this->assertEquals('t', $prefixedQuery->where[0]->subQuery->where[2]->values->tableAlias);
        $this->assertAttributes(['column1' => 't.name', 'column2' => 'c.title'], $prefixedQuery->where[0]->subQuery->where[2]->values->where[0]);
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
        $this->assertEquals('prefix_table.column1', $processor->addTablePrefixToColumn('table.column1', ['t', 'c']));
        $this->assertEquals('database.prefix_table.column1', $processor->addTablePrefixToColumn('database.table.column1'));
        $this->assertEquals('t.column1', $processor->addTablePrefixToColumn('t.column1', ['t', 'c']));
    }

    /**
     * Tests that the empty prefix is added correctly
     */
    public function testEmptyPrefix()
    {
        $processor = new TablePrefixer('');

        $query = (new Query())
            ->from('posts')
            ->addSelect('posts.title', 'title')
            ->whereExists(function (Query $query) {
                $query
                    ->from('comments', 'c')
                    ->whereColumn('c.post_id', 'posts.id');
            });

        $prefixedQuery = $processor->process($query);

        $this->assertEquals($prefixedQuery, $query);
        $this->assertEquals('posts', $prefixedQuery->table);
        $this->assertEquals(['title' => 'posts.title'], $prefixedQuery->select);
        $this->assertEquals('comments', $prefixedQuery->where[0]->subQuery->table);
        $this->assertAttributes(['column1' => 'c.post_id', 'column2' => 'posts.id'], $prefixedQuery->where[0]->subQuery->where[0]);
    }
}
