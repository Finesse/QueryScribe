<?php

namespace Finesse\QueryScribe\Grammars;

use Finesse\QueryScribe\Exceptions\InvalidQueryException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Orders\ExplicitOrder;
use Finesse\QueryScribe\QueryBricks\Orders\Order;
use Finesse\QueryScribe\QueryBricks\Orders\OrderByIsNull;

/**
 * A set of methods to implement order in the common grammar
 *
 * @author Surgie
 */
trait CommonGrammarOrderTrait
{
    /**
     * Compiles a ORDER part of an SQL query.
     *
     * @param Query $query Query data
     * @param array $bindings Bound values (array is filled by reference)
     * @return string SQL text
     * @throws InvalidQueryException
     */
    protected function compileOrderPart(Query $query, array &$bindings): string
    {
        $ordersSQL = [];

        foreach ($query->order as $order) {
            $orderSQL = $this->compileOneOrder($order, $bindings);

            if ($orderSQL !== '') {
                $ordersSQL[] = $orderSQL;
            }
        }

        return $ordersSQL ? 'ORDER BY '.implode(', ', $ordersSQL) : '';
    }

    /**
     * Converts a single order to an SQL query text.
     *
     * @param Order|OrderByIsNull|ExplicitOrder|string $order Order. String `random` means that the order should be random.
     * @param array $bindings Bound values (array is filled by link)
     * @return string SQL text or an empty string
     * @throws InvalidQueryException
     */
    protected function compileOneOrder($order, array &$bindings): string
    {
        if ($order instanceof Order) {
            return $this->compilePlainOrder($order, $bindings);
        }
        if ($order instanceof OrderByIsNull) {
            return $this->compileOrderByIsNull($order, $bindings);
        }
        if ($order instanceof ExplicitOrder) {
            return $this->compileExplicitOrder($order, $bindings);
        }
        if ($order === 'random') {
            return $this->compileRandomOrder($bindings);
        }
        throw new InvalidQueryException(sprintf(
            'The given order `%s` is unknown',
            is_string($order) ? $order : gettype($order)
        ));
    }

    /**
     * @see compileOneOrder For parameters and return value description
     */
    protected function compilePlainOrder(Order $order, array &$bindings): string
    {
        return $this->compileIdentifier($order->column, $bindings).' '.($order->isDescending ? 'DESC' : 'ASC');
    }

    /**
     * @see compileOneOrder For parameters and return value description
     */
    protected function compileOrderByIsNull(OrderByIsNull $order, array &$bindings): string
    {
        return $this->compileIdentifier($order->column, $bindings).' IS'.($order->areNullFirst ? ' NOT' : '').' NULL';
    }

    /**
     * @see compileOneOrder For parameters and return value description
     */
    protected function compileExplicitOrder(ExplicitOrder $order, array &$bindings): string
    {
        if (!$order->order) {
            return '';
        }

        $sql = 'CASE '.$this->compileIdentifier($order->column, $bindings);
        foreach (array_values($order->order) as $index => $value) {
            $sql .= ' WHEN '.$this->compileValue($value, $bindings).' THEN ?';
            $this->mergeBindings($bindings, [$index]);
        }
        $sql .= ' ELSE ?';
        $this->mergeBindings($bindings, [$order->areOtherFirst ? -1 : count($order->order)]);
        return $sql;
    }

    /**
     * @see compileOneOrder For parameters and return value description
     */
    protected function compileRandomOrder(array &$bindings): string
    {
        return 'RANDOM()';
    }
}
