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
     * Compiles a query object to a INSERT SQL queries. An array of rows is returned because not all DBMS systems
     * support inserting many rows at once.
     *
     * @param Query $query
     * @return StatementInterface[]
     * @throws InvalidQueryException
     */
    public function compileInsert(Query $query): array;

    /**
     * Compiles a query object to a UPDATE SQL query.
     *
     * @param Query $query
     * @return StatementInterface
     * @throws InvalidQueryException
     */
    public function compileUpdate(Query $query): StatementInterface;

    /**
     * Compiles a query object to a DELETE SQL query.
     *
     * @param Query $query
     * @return StatementInterface
     * @throws InvalidQueryException
     */
    public function compileDelete(Query $query): StatementInterface;

    /**
     * Wraps a identifier (table name, column, database, etc.) with quotes. Considers . (split) and * (all columns), for
     * example `table.*`.
     *
     * @param string $identifier
     * @return string
     */
    public function quoteCompositeIdentifier(string $identifier): string;

    /**
     * Wraps a plain (without nesting by dots) identifier (table name, column, database, etc.) with quotes and screens
     * inside quotes. Must wrap everything even . and *.
     *
     * @param string $identifier
     * @return string
     */
    public function quoteIdentifier(string $identifier): string;

    /**
     * Escapes the LIKE operator special characters. Doesn't escape general string wildcard characters because it is
     * another job.
     *
     * @param string $string
     * @return string
     */
    public function escapeLikeWildcards(string $string): string;
}
