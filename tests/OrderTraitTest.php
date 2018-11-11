<?php

namespace Finesse\QueryScribe\Tests;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Orders\ExplicitOrder;
use Finesse\QueryScribe\QueryBricks\Orders\Order;
use Finesse\QueryScribe\QueryBricks\Orders\OrderByIsNull;
use Finesse\QueryScribe\Raw;

/**
 * Tests the OrderTrait trait
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

    /**
     * Tests the orderByNullFirst and orderByNullLast methods
     */
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
     * Tests the inExplicitOrder method
     */
    public function testInExplicitOrder()
    {
        $query = (new Query)
            ->inExplicitOrder('name', ['Alice', 'Bob', function (Query $query) {
                $query->addSelect('login')->from('users');
            }])
            ->inExplicitOrder('group', [4, new Raw('NOW()'), 3, 6], true);

        $this->assertAttributeEquals([
            new ExplicitOrder('name', ['Alice', 'Bob', (new Query)->addSelect('login')->from('users')], false),
            new ExplicitOrder('group', [4, new Raw('NOW()'), 3, 6], true)
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
