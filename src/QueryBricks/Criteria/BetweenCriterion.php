<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * BETWEEN criterion.
 *
 * @author Surgie
 */
class BetweenCriterion extends Criterion
{
    /**
     * @var string|Query|StatementInterface Target column (with prefix)
     */
    public $column;

    /**
     * @var mixed|Query|StatementInterface|null Left value
     */
    public $min;

    /**
     * @var mixed|Query|StatementInterface|null Right value
     */
    public $max;

    /**
     * @var bool Whether the rule should be NOT BETWEEN
     */
    public $not;

    /**
     * {@inheritDoc}
     *
     * @param $column string|Query|StatementInterface Target column (with prefix)
     * @param $min mixed|Query|StatementInterface|null Left value
     * @param $max mixed|Query|StatementInterface|null Right value
     * @param bool $not Whether the rule should be NOT BETWEEN
     */
    public function __construct($column, $min, $max, bool $not, int $appendRule)
    {
        parent::__construct($appendRule);
        $this->column = $column;
        $this->min = $min;
        $this->max = $max;
        $this->not = $not;
    }
}
