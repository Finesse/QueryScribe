<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * BETWEEN criterion.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
class BetweenCriterion extends Criterion
{
    /**
     * @var string|Query|StatementInterface Target column (with prefix)
     * @readonly
     */
    public $column;

    /**
     * @var mixed|Query|StatementInterface|null Left value
     * @readonly
     */
    public $min;

    /**
     * @var mixed|Query|StatementInterface|null Right value
     * @readonly
     */
    public $max;

    /**
     * @var bool Whether the rule should be NOT BETWEEN
     * @readonly
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
    public function __construct($column, $min, $max, bool $not, string $appendRule)
    {
        parent::__construct($appendRule);
        $this->column = $column;
        $this->min = $min;
        $this->max = $max;
        $this->not = $not;
    }
}
