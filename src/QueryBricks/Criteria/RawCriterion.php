<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * A criterion represented by a raw SQL.
 *
 * @author Surgie
 */
class RawCriterion extends Criterion
{
    /**
     * @var StatementInterface A raw SQL criteria
     */
    public $raw;

    /**
     * {@inheritDoc}
     *
     * @param StatementInterface $raw A raw SQL criteria
     */
    public function __construct(StatementInterface $raw, $appendRule)
    {
        parent::__construct($appendRule);
        $this->raw = $raw;
    }
}
