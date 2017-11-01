<?php

namespace Finesse\QueryScribe;

/**
 * An object which has a Query object that can be retrieved.
 *
 * @author Surgie
 */
interface HasQueryInterface
{
    /**
     * Returns a stored Query object.
     *
     * @return Query
     */
    public function getBaseQuery(): Query;
}
