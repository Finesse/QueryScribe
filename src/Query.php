<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\QueryBricks\SelectTrait;
use Finesse\QueryScribe\QueryBricks\WhereTrait;

/**
 * Represents a built query. It contains only a basic query data, not a SQL text.
 *
 * All the callable mentioned here as a value type are the function of the following type (if other is not specified):
 *  - Takes an empty query the first argument;
 *  - Returns a SELECT query object or modifies the given object by link.
 *
 * @author Surgie
 */
class Query
{
    use AddTablePrefixTrait, MakeRawTrait;
    use SelectTrait, WhereTrait;

    /**
     * @var string|self|StatementInterface|null Query target table name (prefixed)
     */
    public $from = null;

    /**
     * @var string|null Target table alias
     */
    public $fromAlias = null;

    /**
     * @var int|self|StatementInterface|null Offset
     */
    public $offset = null;

    /**
     * @var int|self|StatementInterface|null Limit
     */
    public $limit = null;

    /**
     * @param string $tablePrefix Prefix for all the tables (except raws)
     */
    public function __construct(string $tablePrefix = '')
    {
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * Sets the target table.
     *
     * @param string|callable|Query|StatementInterface $table Not prefixed table name without quotes
     * @param string|null Table alias
     * @return self Itself
     * @throws InvalidArgumentException
     */
    public function from($table, string $alias = null): self
    {
        $table = $this->checkStringValue('Argument $table', $table);

        $this->from = is_string($table) ? $this->addTablePrefix($table) : $table;
        $this->fromAlias = $alias;
        return $this;
    }

    /**
     * Sets the offset.
     *
     * @param int|callable|self|StatementInterface|null $offset Offset. Null removes the offset.
     * @return self Itself
     */
    public function offset($offset): self
    {
        $this->offset = $this->checkIntOrNullValue('Argument $offset', $offset);
        return $this;
    }

    /**
     * Sets the limit.
     *
     * @param int|callable|self|StatementInterface|null $limit Limit. Null removes the limit.
     * @return self Itself
     */
    public function limit($limit): self
    {
        $this->limit = $this->checkIntOrNullValue('Argument $limit', $limit);
        return $this;
    }

    /**
     * Check that value is suitable for being a "string or subquery" property of a query. Retrieves the callable
     * subquery.
     *
     * @param string $name Value name
     * @param string|callable|self|StatementInterface $value
     * @return string|self|StatementInterface
     * @throws InvalidArgumentException
     */
    protected function checkStringValue(string $name, $value)
    {
        if (
            !is_string($value) &&
            !is_callable($value) &&
            !($value instanceof self) &&
            !($value instanceof StatementInterface)
        ) {
            throw InvalidArgumentException::create(
                $name,
                $value,
                ['string', 'callable', self::class, StatementInterface::class]
            );
        }

        if (is_callable($value)) {
            return $this->retrieveCallableQuery($value, $this->makeCopyForSubQuery());
        }

        return $value;
    }

    /**
     * Check that value is suitable for being a "int or null or subquery" property of a query. Retrieves the callable
     * subquery.
     *
     * @param string $name Value name
     * @param int|callable|self|StatementInterface|null $value
     * @return int|self|StatementInterface|null
     * @throws InvalidArgumentException
     */
    protected function checkIntOrNullValue(string $name, $value)
    {
        if (
            $value !== null &&
            !is_numeric($value) &&
            !is_callable($value) &&
            !($value instanceof self) &&
            !($value instanceof StatementInterface)
        ) {
            throw InvalidArgumentException::create(
                $name,
                $value,
                ['integer', 'callable', self::class, StatementInterface::class, 'null']
            );
        }

        if (is_numeric($value)) {
            $value = (int)$value;
        } elseif (is_callable($value)) {
            return $this->retrieveCallableQuery($value, $this->makeCopyForSubQuery());
        }

        return $value;
    }

    /**
     * Check that value is suitable for being a "scalar or null or subquery" property of a query. Retrieves the callable
     * subquery.
     *
     * @param string $name Value name
     * @param mixed|callable|self|StatementInterface|null $value
     * @return mixed|self|StatementInterface|null
     * @throws InvalidArgumentException
     */
    protected function checkScalarOrNullValue(string $name, $value)
    {
        if (
            $value !== null &&
            !is_scalar($value) &&
            !is_callable($value) &&
            !($value instanceof self) &&
            !($value instanceof StatementInterface)
        ) {
            throw InvalidArgumentException::create(
                $name,
                $value,
                ['scalar', 'callable', self::class, StatementInterface::class, 'null']
            );
        }

        if (is_callable($value)) {
            return $this->retrieveCallableQuery($value, $this->makeCopyForSubQuery());
        }

        return $value;
    }

    /**
     * Check that value is suitable for being a column name of a query. Retrieves the callable subquery. Adds a table
     * prefix to a column name (if it contains a table name).
     *
     * @param string $name Value name
     * @param string|callable|self|StatementInterface $column
     * @return string|self|StatementInterface
     * @throws InvalidArgumentException
     */
    protected function checkAndPrepareColumn(string $name, $column)
    {
        $column = $this->checkStringValue($name, $column);
        if (is_string($column)) {
            $column = $this->addTablePrefixToColumn($column);
        }
        return $column;
    }

    /**
     * Retrieves the subquery from a callable.
     *
     * @param callable $callback
     * @param self $emptyQuery Empty query object suitable for the callback
     * @return self
     */
    protected function retrieveCallableQuery(callable $callback, self $emptyQuery): self
    {
        $result = $callback($emptyQuery);

        if ($result instanceof self) {
            return $result;
        } else {
            return $emptyQuery;
        }
    }

    /**
     * Makes a self copy with dependencies for passing to a subquery callback.
     *
     * @return self
     */
    protected function makeCopyForSubQuery(): self
    {
        return new static($this->tablePrefix);
    }

    /**
     * Makes a self copy with dependencies for passing to a criteria group callback.
     *
     * @return self
     */
    protected function makeCopyForCriteriaGroup(): self
    {
        return (new static($this->tablePrefix))->from($this->from);
    }
}
