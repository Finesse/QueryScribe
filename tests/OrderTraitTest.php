<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Order;

/**
 * Tests the Query class
 *
 * @author Surgie
 */
class OrderTraitTest extends TestCase
{
    /**
     * Tests the orderBy method
     */
    public function testOrderBy()
    {
        $query = (new Query())
            ->from('post')
            ->orderBy('name')
            ->orderBy(function (Query $query) {
                $query
                    ->addAvg('price')
                    ->table('products')
                    ->whereColumn('post.category_id', 'price.category_id');
            }, 'desc');

        $this->assertCount(2, $query->order);
        $this->assertInstanceOf(Order::class, $query->order[0]);
        $this->assertAttributes(['column' => 'name', 'isDescending' => false], $query->order[0]);
        $this->assertInstanceOf(Order::class, $query->order[1]);
        $this->assertEquals(true, $query->order[1]->isDescending);
        $this->assertInstanceOf(Query::class, $query->order[1]->column);
        $this->assertEquals('products', $query->order[1]->column->table);
    }


    /**
     * Tests the inRandomOrder method
     */
    public function testInRandomOrder()
    {
        $query = (new Query())
            ->from('post')
            ->inRandomOrder();

        $this->assertCount(1, $query->order);
        $this->assertEquals('random', $query->order[0]);
    }
}
