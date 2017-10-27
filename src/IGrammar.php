<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\Common\IQueryable;

/**
 * Converts query data to a SQL text. Implementing classes should adapt SQL text for different DBMS (database management
 * systems).
 *
 * @author Surgie
 */
interface IGrammar
{
    /**
     * Compiles a query object to a SELECT SQL query.
     *
     * @param Query $query
     * @return IQueryable
     * @throws IException
     */
    public function makeSelect(Query $query): IQueryable;
}
