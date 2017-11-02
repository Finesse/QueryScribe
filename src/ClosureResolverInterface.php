<?php

namespace Finesse\QueryScribe;

/**
 * Represents an object that turns closures to Query objects.
 *
 * @author Surgie
 */
interface ClosureResolverInterface
{
    /**
     * Resolves a closure given instead of a subquery.
     *
     * @param \Closure $callback
     * @return Query
     */
    public function resolveSubQueryClosure(\Closure $callback): Query;

    /**
     * Resolves a closure given instead of a criteria group query.
     *
     * @param \Closure $callback
     * @return Query
     */
    public function resolveCriteriaGroupClosure(\Closure $callback): Query;
}
