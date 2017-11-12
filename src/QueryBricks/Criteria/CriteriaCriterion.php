<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\QueryBricks\Criterion;

/**
 * A group of criteria.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
class CriteriaCriterion extends Criterion
{
    /**
     * @var Criterion[] Criteria of the group
     * @readonly
     */
    public $criteria;

    /**
     * @var bool Whether the group should be wrapped with NOT
     * @readonly
     */
    public $not;

    /**
     * {@inheritDoc}
     *
     * @param Criterion[] $criteria Criteria of the group
     * @param bool $not Whether the group should be wrapped with NOT
     */
    public function __construct(array $criteria, bool $not, string $appendRule)
    {
        parent::__construct($appendRule);
        $this->criteria = $criteria;
        $this->not = $not;
    }
}
