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
     * Adds an order by is null to the orders list.
     *
     * @param string|\Closure|self|StatementInterface $column Column to order by
     * @param boolean $nullFirst Must the null values go first; otherwise they will go last
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orderByIsNull($column, bool $nullFirst = false): self
    {
        $column = $this->checkStringValue('Argument $column', $column);
        $this->order[] = new OrderByIsNull($column, $nullFirst);
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
