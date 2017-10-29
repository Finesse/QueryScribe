<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * Criterion which compares two columns
 *
 * @author Surgie
 */
class ColumnsCriterion extends Criterion
{
    /**
     * @var string|Query|StatementInterface Target column 1 (with prefix)
     */
    public $column1;

    /**
     * @var string Compare rule (=, >, etc.)
     */
    public $rule;

    /**
     * @var string|Query|StatementInterface Target column 2 (with prefix)
     */
    public $column2;

    /**
     * {@inheritDoc}
     *
     * @param $column1 string|Query|StatementInterface Target column 1 (with prefix)
     * @param $rule string Compare rule (=, >, etc.)
     * @param $column2 string|Query|StatementInterface Target column 2 (with prefix)
     */
    public function __construct($column1, string $rule, $column2, int $appendRule)
    {
        parent::__construct($appendRule);
        $this->column1 = $column1;
        $this->rule = $rule;
        $this->column2 = $column2;
    }
}
