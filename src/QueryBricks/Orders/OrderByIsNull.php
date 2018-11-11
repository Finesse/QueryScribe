<?php

namespace Finesse\QueryScribe\QueryBricks\Orders;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\StatementInterface;

/**
 * One order by is null for the ORDER section.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
class OrderByIsNull
{
    /**
     * @var string|Query|StatementInterface Target column
     * @readonly
     */
    public $column;

    /**
     * @var bool Must the null values go first
     * @readonly
     */
    public $nullFirst;

    /**
     * @param string|Query|StatementInterface $column Target column
     * @param bool $nullFirst Must the null values go first
     */
    public function __construct($column, bool $nullFirst)
    {
        $this->column = $column;
        $this->nullFirst = $nullFirst;
    }
}
