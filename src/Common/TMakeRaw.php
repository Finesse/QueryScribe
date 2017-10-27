<?php

namespace Finesse\QueryScribe\Common;

use Finesse\QueryScribe\Raw;

/**
 * Helps to create raw query objects.
 *
 * @author Surgie
 */
trait TMakeRaw
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
