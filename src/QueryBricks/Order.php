<?php

namespace Finesse\QueryScribe\QueryBricks;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\StatementInterface;

/**
 * One order for the ORDER section.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
class Order
{
    /**
     * @var string|Query|StatementInterface Target column (with prefix)
     * @readonly
     */
    public $column;

    /**
     * @var bool Should the order be ascending (true) or descending (false)
     * @readonly
     */
    public $isDescending;

    /**
     * @param string|Query|StatementInterface $column Target column (with prefix)
     * @param bool $isDescending Should the order be ascending (true) or descending (false)
     */
    public function __construct($column, bool $isDescending)
    {
        $this->column = $column;
        $this->isDescending = $isDescending;
    }
}
