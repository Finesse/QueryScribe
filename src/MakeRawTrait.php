<?php

namespace Finesse\QueryScribe;

/**
 * Helps to create raw query objects.
 *
 * @author Surgie
 */
trait MakeRawTrait
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
}
