<?php

namespace Finesse\QueryScribe\QueryBricks;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\StatementInterface;

/**
 * An aggregate (min, sum, etc.) function for the SELECT part of a query.
 *
 * @author Surgie
 */
class Aggregate
{
    /**
     * @var string Aggregate function name
     */
    public $function;

    /**
     * @var string|Query|StatementInterface Value to aggregate (prefixed column name or a subquery)
     */
    public $column;

    /**
     * Aggregate constructor.
     *
     * @param string $function Aggregate function name
     * @param string|Query|StatementInterface $column Value to aggregate (prefixed column name or a subquery)
     */
    public function __construct(string $function, $column)
    {
        $this->function = $function;
        $this->column = $column;
    }
}
