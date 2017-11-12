<?php

namespace Finesse\QueryScribe\QueryBricks\Criteria;

use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * A criterion represented by a raw SQL.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
class RawCriterion extends Criterion
{
    /**
     * @var StatementInterface A raw SQL criteria
     * @readonly
     */
    public $raw;

    /**
     * {@inheritDoc}
     *
     * @param StatementInterface $raw A raw SQL criteria
     */
    public function __construct(StatementInterface $raw, string $appendRule)
    {
        parent::__construct($appendRule);
        $this->raw = $raw;
    }
}
