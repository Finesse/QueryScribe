<?php

namespace Finesse\QueryScribe\PostProcessors;

use Finesse\QueryScribe\Query;

/**
 * Adds table names or aliases to all the column names that don't have a table name. E.g. turns
 * `SELECT column1, table.column2 FROM table` into `SELECT table.column1, table.column2 FROM table`.
 *
 * @author Surgie
 */
class ExplicitTables extends AbstractProcessor
{
    /**
     * {@inheritDoc}
     */
    protected function getInitialContext(Query $query)
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function processQuery(Query $query, $context): Query
    {
        return parent::processQuery($query, $query->getTableIdentifier());
    }

    /**
     * {@inheritDoc}
     *
     * @param string|null $tableIdentifier The current subquery table name or alias
     */
    protected function processColumnName(string $column, $tableIdentifier): string
    {
        $column = parent::processColumnName($column, $tableIdentifier);

        if ($tableIdentifier !== null && strpos($column, '.') === false) {
            return $tableIdentifier.'.'.$column;
        } else {
            return $column;
        }
    }
}
