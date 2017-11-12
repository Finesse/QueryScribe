<?php

namespace Finesse\QueryScribe\QueryBricks;

/**
 * A query criterion (a statement that returns true of false). Used in WHERE, HAVING, JOIN.
 *
 * You MUST NOT change the public variables values.
 *
 * @author Surgie
 */
abstract class Criterion
{
    /**
     * @var string Append rule. The value is SQL boolean operator name (in uppercase).
     * @readonly
     */
    public $appendRule;

    /**
     * @param int $appendRule Append rule. The value is SQL boolean operator name.
     */
    public function __construct(string $appendRule)
    {
        $this->appendRule = strtoupper($appendRule);
    }
}
