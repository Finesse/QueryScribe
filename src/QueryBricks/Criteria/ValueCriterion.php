<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * Criterion which compares a column with a value.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
class ValueCriterion extends Criterion
{
    /**
     * @var string|Query|StatementInterface Target column
     * @readonly
     */
    public $column;

    /**
     * @var string Compare rule (=, >, etc.) (in uppercase)
     * @readonly
     */
    public $rule;

    /**
     * @var mixed|Query|StatementInterface|null Value
     * @readonly
     */
    public $value;

    /**
     * {@inheritDoc}
     *
     * @param $column string|Query|StatementInterface Target column
     * @param $rule string Compare rule (=, >, etc.)
     * @param $value mixed|Query|StatementInterface|null Value
     */
    public function __construct($column, string $rule, $value, string $appendRule)
    {
        parent::__construct($appendRule);
        $this->column = $column;
        $this->rule = strtoupper($rule);
        $this->value = $value;
    }
}
