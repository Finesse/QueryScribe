<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;

/**
 * Helps to make a Query object extensions which don't fully inherit the Query interface. It keeps an original Query
 * object and passes all the methods calls to it. It helps to substitute the value passed to closures.
 *
 * @mixin Query
 *
 * @author Surgie
 */
class QueryProxy implements ClosureResolverInterface
{
    /**
     * @var Query A real underlying query object
     */
    protected $baseQuery;

    /**
     * @var string[] Which methods should not be proxied to the underlying query object
     */
    protected $doNotProxy = [
        'setClosureResolver',
        'makeEmptyCopy',
        'makeCopyForSubQuery',
        'makeCopyForCriteriaGroup'
    ];

    /**
     * @param Query $baseQuery Underlying query object
     */
    public function __construct(Query $baseQuery)
    {
        $this->baseQuery = $baseQuery->setClosureResolver($this);
    }

    /**
     * {@inheritDoc}
     * All the exception from the underlying query are sent to the `handleBaseQueryException` method.
     *
     * @throws \Error If the given method is not defined in a base query or forbidden
     */
    public function __call($name, $arguments)
    {
        if (in_array($name, $this->doNotProxy)) {
            throw new \Error(sprintf('Call to undefined method %s::%s()', static::class, $name));
        }

        try {
            $result = $this->baseQuery->$name(...$arguments);
        } catch (\Throwable $exception) {
            return $this->handleBaseQueryException($exception);
        }

        // If the base query returns itself, this object should also return itself
        if ($result === $this->baseQuery) {
            return $this;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function __clone()
    {
        $this->baseQuery = clone $this->baseQuery;
    }

    /**
     * {@inheritDoc}
     */
    public function resolveSubQueryClosure(\Closure $callback): Query
    {
        $query = new static($this->baseQuery->makeCopyForSubQuery());
        return $this->resolveClosure($callback, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function resolveCriteriaGroupClosure(\Closure $callback): Query
    {
        $query = new static($this->baseQuery->makeCopyForCriteriaGroup());
        return $this->resolveClosure($callback, $query);
    }

    /**
     * Returns the underlying real query object.
     *
     * @return Query
     */
    protected function getBaseQuery(): Query
    {
        return $this->baseQuery;
    }

    /**
     * Handles exceptions thrown by the underlying query.
     *
     * @param \Throwable $exception Thrown exception
     * @throws \Throwable It may rethrow them
     */
    protected function handleBaseQueryException(\Throwable $exception)
    {
        throw $exception;
    }

    /**
     * Retrieves the query object from a closure.
     *
     * @param \Closure $callback
     * @param self $emptyQuery Empty query object suitable for the callback
     * @return Query Retrieved query
     * @throws InvalidReturnValueException
     */
    protected function resolveClosure(\Closure $callback, self $emptyQuery): Query
    {
        $result = $callback($emptyQuery) ?? $emptyQuery;

        if ($result instanceof self) {
            return $result->getBaseQuery();
        }
        if ($result instanceof Query) {
            return $result;
        }

        throw InvalidReturnValueException::create(
            'The closure return value',
            $result,
            ['null', self::class, Query::class]
        );
    }
}
