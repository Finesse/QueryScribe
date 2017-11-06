<?php

namespace Finesse\QueryScribe\Grammars;

use Finesse\QueryScribe\Exceptions\InvalidQueryException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\StatementInterface;

/**
 * A grammar that compiles queries to the SQLite SQL dialect
 *
 * @author Surgie
 */
class SQLiteGrammar extends CommonGrammar
{
    /**
     * {@inheritDoc}
     */
    public function compileUpdate(Query $query): StatementInterface
    {
        if ($query->tableAlias !== null) {
            throw new InvalidQueryException('Table alias is not allowed in update query');
        }

        return parent::compileUpdate($query);
    }

    /**
     * {@inheritDoc}
     */
    public function compileDelete(Query $query): StatementInterface
    {
        if ($query->tableAlias !== null) {
            throw new InvalidQueryException('Table alias is not allowed in delete query');
        }

        return parent::compileDelete($query);
    }

    /**
     * {@inheritDoc}
     */
    protected function compileInsertFromValues($table, string $tableAlias = null, array $values): array
    {
        $statements = [];

        foreach ($values as $row) {
            foreach (parent::compileInsertFromValues($table, $tableAlias, [$row]) as $statement) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }
}
