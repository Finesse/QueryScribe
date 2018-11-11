<?php

namespace Finesse\QueryScribe;

/**
 * Represents a column name in an SQL query
 *
 * @author Surgie
 */
class Column implements StatementInterface
{
    /**
     * @var string The column name
     * @readonly
     */
    public $column;

    /**
     * @param string $column The column name
     */
    public function __construct(string $column)
    {
        $this->column = $column;
    }

    /**
     * {@inheritDoc}
     * Caution! It is unescaped so we don't recommend to insert it to an SQL as is.
     */
    public function getSQL(): string
    {
        return $this->column;
    }

    /**
     * {@inheritDoc}
     */
    public function getBindings(): array
    {
        return [];
    }
}
