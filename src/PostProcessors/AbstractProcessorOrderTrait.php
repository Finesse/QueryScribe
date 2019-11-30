<?php

namespace Finesse\QueryScribe\PostProcessors;

use Finesse\QueryScribe\QueryBricks\Orders\ExplicitOrder;
use Finesse\QueryScribe\QueryBricks\Orders\Order;
use Finesse\QueryScribe\QueryBricks\Orders\OrderByIsNull;

/**
 * A set of methods to implement order in the abstract processor
 *
 * @author Surgie
 */
trait AbstractProcessorOrderTrait
{
    /**
     * Processes a single order statement.
     *
     * @param Order|OrderByIsNull|ExplicitOrder|string $order
     * @param mixed $context The processing context
     * @return Order|string
     */
    protected function processOrder($order, $context)
    {
        if ($order instanceof Order) {
            return $this->processPlainOrder($order, $context);
        }
        if ($order instanceof OrderByIsNull) {
            return $this->processOrderByIsNull($order, $context);
        }
        if ($order instanceof ExplicitOrder) {
            return $this->processExplicitOrder($order, $context);
        }
        return $order;
    }

    /**
     * @see processOrder For parameters and return value description
     */
    protected function processPlainOrder(Order $order, $context): Order
    {
        $column = $this->processColumnOrSubQuery($order->column, $context);

        if ($column === $order->column) {
            return $order;
        } else {
            return new Order($column, $order->isDescending);
        }
    }

    /**
     * @see processOrder For parameters and return value description
     */
    protected function processOrderByIsNull(OrderByIsNull $order, $context): OrderByIsNull
    {
        $column = $this->processColumnOrSubQuery($order->column, $context);

        if ($column === $order->column) {
            return $order;
        } else {
            return new OrderByIsNull($column, $order->areNullFirst);
        }
    }

    /**
     * @see processOrder For parameters and return value description
     */
    protected function processExplicitOrder(ExplicitOrder $order, $context): ExplicitOrder
    {
        $column = $this->processColumnOrSubQuery($order->column, $context);

        $values = [];
        foreach ($order->order as $index => $value) {
            $values[$index] = $this->processValueOrSubQuery($value, $context);
        }

        if ($column === $order->column && $values === $order->order) {
            return $order;
        } else {
            return new ExplicitOrder($column, $values, $order->areOtherFirst);
        }
    }
}
