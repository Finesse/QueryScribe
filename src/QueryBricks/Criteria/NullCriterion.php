<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * Checks whether a value is null or not
 *
 * @author Surgie
 */
class NullCriterion extends Criterion
{
    /**
     * @var string|Query|StatementInterface Target column (with prefix)
     */
    public $column;

    /**
     * @var bool Should a value be null
     */
    public $isNull;

    /**
     * {@inheritDoc}
     *
     * @param bool $isNull Should a value be null
     */
    public function __construct($column, bool $isNull, int $appendRule)
    {
        parent::__construct($appendRule);
        $this->column = $column;
        $this->isNull = $isNull;
    }
}
