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
        $query = (new Query)
            ->from('post')
            ->orderBy('name')
            ->orderBy(function (Query $query) {
                $query
                    ->addAvg('price')
                    ->table('products')
                    ->whereColumn('post.category_id', 'price.category_id');
            }, 'desc');

        $this->assertEquals([
            new Order('name', false),
            new Order((new Query)->addAvg('price')->table('products')->whereColumn('post.category_id', 'price.category_id'), true)
        ], $query->order);
    }


    /**
     * Tests the inRandomOrder method
     */
    public function testInRandomOrder()
    {
        $query = (new Query)
            ->from('post')
            ->inRandomOrder();

        $this->assertEquals(['random'], $query->order);
    }
}
