<?php

namespace Finesse\QueryScribe\Grammars;

use Finesse\QueryScribe\QueryBricks\Criteria\ValueCriterion;
use Finesse\QueryScribe\QueryBricks\Orders\ExplicitOrder;

/**
 * A grammar that compiles queries to the MySQL SQL dialect
 *
 * @author Surgie
 */
class MySQLGrammar extends CommonGrammar
{
    /**
     * {@inheritDoc}
     */
    public function quoteIdentifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /**
     * {@inheritDoc}
     */
    protected function compileValueCriterion(ValueCriterion $criterion, array &$bindings): string
    {
        return sprintf(
            '%s %s %s',
            $this->compileIdentifier($criterion->column, $bindings),
            $criterion->rule,
            $this->compileValue($criterion->value, $bindings)
        );
    }

    /**
     * {@inheritDoc}
     * The common variant works too but this one is a bit faster.
     */
    protected function compileExplicitOrder(ExplicitOrder $order, array &$bindings): string
    {
        if (!$order->order) {
            return '';
        }

        // array_reverse is the fastest way to walk an array backwards: https://3v4l.org/3jaTT
        $orderList = $order->areOtherFirst ? $order->order : array_reverse($order->order);
        $values = [];
        foreach ($orderList as $value) {
            $values[] = $this->compileValue($value, $bindings);
        }
        return sprintf(
            'FIELD(%s, %s) %s',
            $this->compileIdentifier($order->column, $bindings),
            implode(', ', $values),
            $order->areOtherFirst ? 'ASC' : 'DESC'
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function compileRandomOrder(array &$bindings): string
    {
        return 'RAND()';
    }
}
