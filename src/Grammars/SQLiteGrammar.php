<?php

namespace Finesse\QueryScribe\Grammars;

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
