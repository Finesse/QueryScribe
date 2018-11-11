<?php

namespace Finesse\QueryScribe\QueryBricks;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Orders\ExplicitOrder;
use Finesse\QueryScribe\QueryBricks\Orders\Order;
use Finesse\QueryScribe\QueryBricks\Orders\OrderByIsNull;
use Finesse\QueryScribe\StatementInterface;

/**
 * Contains properties and methods that add a possibility to use the ORDER BY section in a query.
 *
 * @author Surgie
 */
trait OrderTrait
{
    /**
     * @var Order[]|OrderByIsNull[]|ExplicitOrder[]|string[] Orders. String value `random` means that the order should
     *  be random.
     */
    public $order = [];

    /**
     * Adds a simple order to the orders list.
     *
     * @param string|\Closure|self|StatementInterface $column Column to order by
     * @param string $direction Order direction: `asc` - ascending, `desc` - descending
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orderBy($column, string $direction = 'asc'): self
    {
        $column = $this->checkStringValue('Argument $column', $column);
        $this->order[] = new Order($column, strtolower($direction) === 'desc');
        return $this;
    }

    /**
     * Adds such order that the null column values go last.
     *
     * @param string|\Closure|self|StatementInterface $column The column
     * @param boolean $doReverse Do reverse the order (the null values go first)
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orderByNullLast($column, bool $doReverse = false): self
    {
        $column = $this->checkStringValue('Argument $column', $column);
        $this->order[] = new OrderByIsNull($column, $doReverse);
        return $this;
    }

    /**
     * Adds such order that the null column values go first.
     *
     * @param string|\Closure|self|StatementInterface $column The column
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orderByNullFirst($column): self
    {
        return $this->orderByNullLast($column, true);
    }

    /**
     * Adds such order that makes a column values follow the explicitly given order.
     *
     * @param string|\Closure|self|StatementInterface $column The column
     * @param mixed[]|\Closure[]|Query[]|StatementInterface[] $order The values in the required order
     * @param bool $areOtherFirst Must the values not in the list go first; otherwise they will go last
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function inExplicitOrder($column, array $order, bool $areOtherFirst = false): self
    {
        $column = $this->checkStringValue('Argument $column', $column);
        foreach ($order as $index => &$value) {
            $value = $this->checkScalarOrNullValue('Argument $order['.$index.']', $value);
        }

        $this->order[] = new ExplicitOrder($column, $order, $areOtherFirst);
        return $this;
    }

    /**
     * Adds a random order to the orders list.
     *
     * @return $this
     */
    public function inRandomOrder(): self
    {
        $this->order[] = 'random';
        return $this;
    }
}
