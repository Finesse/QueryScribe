<?php

namespace Finesse\QueryScribe\PostProcessors;

use Finesse\QueryScribe\Query;

/**
 * Transforms all the table names of a query regarding table aliases.
 *
 * @ignore Override the `processTableName` and `processColumnName` methods when you extend this class. They receive
 * the list of table names that must processed in the `$context` argument.
 *
 * @author Surgie
 */
abstract class AbstractTableNameProcessor extends AbstractProcessor
{
    /**
     * {@inheritDoc}
     */
    protected function getInitialContext(Query $query)
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @param string[] $knownTables Unprocessed known table names
     */
    public function processQuery(Query $query, $knownTables): Query
    {
        foreach ($this->getTables($query) as $table) {
            $knownTables[] = $table;
        }

        return parent::processQuery($query, $knownTables);
    }

    /**
     * Retrieves unprocessed table names used in a query (excluding subqueries).
     *
     * @param Query $query
     * @return string[]
     */
    protected function getTables(Query $query): array
    {
        $tables = [];

        if (is_string($query->table)) {
            $tables[] = $query->table;
        }

        foreach ($query->join as $join) {
            if (is_string($join->table)) {
                $tables[] = $join->table;
            }
        }

        return $tables;
    }
}
