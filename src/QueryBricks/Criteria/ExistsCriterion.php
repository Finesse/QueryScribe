<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * EXISTS criterion.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
class ExistsCriterion extends Criterion
{
    /**
     * @var Query|StatementInterface Subquery
     * @readonly
     */
    public $subQuery;

    /**
     * @var bool Whether the rule should be NOT IN
     * @readonly
     */
    public $not;

    /**
     * {@inheritDoc}
     *
     * @param $subQuery Query|StatementInterface Subquery
     * @param bool $not Whether the rule should be NOT EXISTS
     */
    public function __construct($subQuery, bool $not, string $appendRule)
    {
        parent::__construct($appendRule);
        $this->subQuery = $subQuery;
        $this->not = $not;
    }
}
