<?php

namespace Finesse\QueryScribe\QueryBricks;

/**
 * A query criterion (a statement that returns true of false). Used in WHERE, HAVING, JOIN.
 *
 * @author Surgie
 */
abstract class Criterion
{
    /**
     * Append rule: AND
     */
    const APPEND_RULE_AND = 1;

    /**
     * Append rule: OR
     */
    const APPEND_RULE_OR = 2;

    /**
     * @var int Append rule. The value if a value of one of the self::APPEND_RULE_* constants.
     */
    public $appendRule;

    /**
     * @param int $appendRule Append rule. The value if a value of one of the self::APPEND_RULE_* constants.
     */
    public function __construct(int $appendRule)
    {
        $this->appendRule = $appendRule;
    }
}
