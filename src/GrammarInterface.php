<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\Exceptions\InvalidQueryException;

/**
 * Converts query data to a SQL text. Implementing classes should adapt SQL text for different DBMS (database management
 * systems).
 *
 * @author Surgie
 */
interface GrammarInterface
{
    /**
     * Compiles a query object guessing it's type.
     *
     * @param Query $query
     * @return StatementInterface
     * @throws InvalidQueryException
     */
    public function compile(Query $query): StatementInterface;

    /**
     * Compiles a query object to a SELECT SQL query.
     *
     * @param Query $query
     * @return StatementInterface
     * @throws InvalidQueryException
     */
    public function compileSelect(Query $query): StatementInterface;

    /**
     * Compiles a query object to a INSERT SQL query.
     *
     * @param Query $query
     * @return StatementInterface
     * @throws InvalidQueryException
     */
    public function compileInsert(Query $query): StatementInterface;
}
