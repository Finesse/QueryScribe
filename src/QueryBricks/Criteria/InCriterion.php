<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * IN criterion.
 *
 * @author Surgie
 */
class InCriterion extends Criterion
{
    /**
     * @var string|Query|StatementInterface Target column (with prefix)
     */
    public $column;

    /**
     * @var mixed[]|Query|StatementInterface Haystack values
     */
    public $values;

    /**
     * @var bool Whether the rule should be NOT IN
     */
    public $not;

    /**
     * {@inheritDoc}
     *
     * @param $column string|Query|StatementInterface Target column (with prefix)
     * @param $values mixed[]|Query|StatementInterface Haystack values
     * @param bool $not Whether the rule should be NOT IN
     */
    public function __construct($column, $values, bool $not, int $appendRule)
    {
        parent::__construct($appendRule);
        $this->column = $column;
        $this->values = $values;
        $this->not = $not;
    }
}
