<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Orders\Order;
use Finesse\QueryScribe\QueryBricks\Orders\OrderByIsNull;

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

        $this->assertAttributeEquals([
            new Order('name', false),
            new Order((new Query)->addAvg('price')->table('products')->whereColumn('post.category_id', 'price.category_id'), true)
        ], 'order', $query);
    }

    public function testOrderByIsNull()
    {
        $query = (new Query)
            ->from('post')
            ->orderByNullLast('name')
            ->orderByNullFirst(function (Query $query) {
                $query->addSelect('price')->from('products');
            });

        $this->assertAttributeEquals([
            new OrderByIsNull('name', false),
            new OrderByIsNull((new Query)->addSelect('price')->from('products'), true)
        ], 'order', $query);
    }

    /**
     * Tests the inRandomOrder method
     */
    public function testInRandomOrder()
    {
        $query = (new Query)
            ->from('post')
            ->inRandomOrder();

        $this->assertAttributeEquals(['random'], 'order', $query);
    }
}
