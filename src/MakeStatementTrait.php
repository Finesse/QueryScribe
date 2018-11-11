<?php

namespace Finesse\QueryScribe;

/**
 * Helps to create simple statement objects (raw, value, etc.).
 *
 * @author Surgie
 */
trait MakeStatementTrait
{
    /**
     * Creates a raw SQL subquery.
     *
     * @param string $query SQL statement
     * @param array $bindings Values to bind to the statement
     * @return Raw
     */
    public function raw(string $query, array $bindings = []): Raw
    {
        return new Raw($query, $bindings);
    }

    /**
     * Creates an explicit value for an SQL query.
     *
     * @param mixed $value The value (not a subquery or a raw statement)
     * @return Value
     */
    public function value($value): Value
    {
        return new Value($value);
    }

    /**
     * Creates a column name for an SQL query.
     *
     * @param string $column The column name
     * @return Column
     */
    public function column(string $column): Column
    {
        return new Column($column);
    }
}
