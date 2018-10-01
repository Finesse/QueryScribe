<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * IN criterion.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
class InCriterion extends Criterion
{
    /**
     * @var string|Query|StatementInterface Target column
     * @readonly
     */
    public $column;

    /**
     * @var mixed[]|Query|StatementInterface Haystack values
     * @readonly
     */
    public $values;

    /**
     * @var bool Whether the rule should be NOT IN
     * @readonly
     */
    public $not;

    /**
     * {@inheritDoc}
     *
     * @param $column string|Query|StatementInterface Target column
     * @param $values mixed[]|Query|StatementInterface Haystack values
     * @param bool $not Whether the rule should be NOT IN
     */
    public function __construct($column, $values, bool $not, string $appendRule)
    {
        parent::__construct($appendRule);
        $this->column = $column;
        $this->values = $values;
        $this->not = $not;
    }
}
