<?php

namespace Finesse\QueryScribe\QueryBricks;

use Finesse\QueryScribe\ClosureResolverInterface;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\Query;

/**
 * Adds methods that turn closures to queries.
 *
 * @author Surgie
 */
trait ResolvesClosuresTrait
{
    /**
     * @var ClosureResolverInterface|null Closure resolver (gets Query's from Closure's)
     */
    protected $closureResolver;

    /**
     * Sets used closure resolver.
     *
     * @param ClosureResolverInterface|null $closureResolver
     * @return $this
     */
    public function setClosureResolver(ClosureResolverInterface $closureResolver = null)
    {
        $this->closureResolver = $closureResolver;
        return $this;
    }

    /**
     * Retrieves the query object from a closure given instead of a subquery.
     *
     * @param \Closure $callback
     * @return Query Retrieved query
     * @throws InvalidReturnValueException
     */
    protected function resolveSubQueryClosure(\Closure $callback): Query
    {
        if ($this->closureResolver === null) {
            return $this->makeCopyForSubQuery()->apply($callback);
        } else {
            return $this->closureResolver->resolveSubQueryClosure($callback);
        }
    }

    /**
     * Retrieves the query object from a closure given instead of a criteria group query.
     *
     * @param \Closure $callback
     * @return Query Retrieved query
     * @throws InvalidReturnValueException
     */
    protected function resolveCriteriaGroupClosure(\Closure $callback): Query
    {
        if ($this->closureResolver === null) {
            return $this->makeCopyForCriteriaGroup()->apply($callback);
        } else {
            return $this->closureResolver->resolveCriteriaGroupClosure($callback);
        }
    }
}
