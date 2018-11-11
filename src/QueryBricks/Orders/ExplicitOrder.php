<?php

namespace Finesse\QueryScribe\QueryBricks\Orders;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\StatementInterface;

/**
 * A sort for the ORDER BY section that makes a column values follow the explicitly given order.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
class ExplicitOrder
{
    /**
     * @var string|Query|StatementInterface Target column
     * @readonly
     */
    public $column;

    /**
     * @var mixed[]|Query[]|StatementInterface[]|null[] The values in the required order
     * @readonly
     */
    public $order;

    /**
     * @var bool Must the values not in the list go first
     * @readonly
     */
    public $areOtherFirst;

    /**
     * @param string|Query|StatementInterface $column Target column
     * @param mixed[]|Query[]|StatementInterface[]|null[] $order The values in the required order
     * @param bool $areOtherFirst Must the values not in the list go first; otherwise they will go last
     */
    public function __construct($column, array $order, bool $areOtherFirst)
    {
        $this->column = $column;
        $this->order = $order;
        $this->areOtherFirst = $areOtherFirst;
    }
}
