<?php

namespace Finesse\QueryScribe\QueryBricks;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
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
     * @var Order[]|OrderByIsNull[]|string[] Orders. String value `random` means that the order should be random.
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
     * @param boolean $reverse Do reverse the order (the null values go first)
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orderByNullLast($column, bool $reverse = false): self
    {
        $column = $this->checkStringValue('Argument $column', $column);
        $this->order[] = new OrderByIsNull($column, $reverse);
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
