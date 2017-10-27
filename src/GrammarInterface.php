<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\Common\StatementInterface;

/**
 * Converts query data to a SQL text. Implementing classes should adapt SQL text for different DBMS (database management
 * systems).
 *
 * @author Surgie
 */
interface GrammarInterface
{
    /**
     * Compiles a query object to a SELECT SQL query.
     *
     * @param Query $query
     * @return StatementInterface
     * @throws ExceptionInterface
     */
    public function makeSelect(Query $query): StatementInterface;
}
