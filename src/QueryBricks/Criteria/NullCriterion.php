<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * Checks whether a value is null or not.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
class NullCriterion extends Criterion
{
    /**
     * @var string|Query|StatementInterface Target column (with prefix)
     * @readonly
     */
    public $column;

    /**
     * @var bool Should a value be null (true) or not null (false)
     * @readonly
     */
    public $isNull;

    /**
     * {@inheritDoc}
     *
     * @param bool $isNull Should a value be null (true) or not null (false)
     */
    public function __construct($column, bool $isNull, string $appendRule)
    {
        parent::__construct($appendRule);
        $this->column = $column;
        $this->isNull = $isNull;
    }
}
