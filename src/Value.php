<?php

namespace Finesse\QueryScribe;

/**
 * Represents an explicit value (number, string, etc.) in an SQL query
 *
 * @author Surgie
 */
class Value implements StatementInterface
{
    /**
     * @var mixed The value (not a subquery or a raw statement)
     * @readonly
     */
    public $value;

    /**
     * @param mixed $value The value (not a subquery or a raw statement)
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function getSQL(): string
    {
        return '?';
    }

    /**
     * {@inheritDoc}
     */
    public function getBindings(): array
    {
        return [$this->value];
    }
}
