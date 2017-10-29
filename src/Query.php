<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidQueryException;

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

    /**
     * @var (string|self|StatementInterface)[] Columns names to select (prefixed). The string indexes are the
     *    aliases names. If no columns are provided, all columns should be selected.
     */
    public $select = [];

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
     * @param string $tablePrefix Tables prefix
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
     * Adds column or columns to the SELECT section.
     *
     * @param string|callable|self|StatementInterface|(string|callable|self|StatementInterface)[] $columns Columns to
     *     add. If string or raw, one column is added. If array, many columns are added and string indexes are treated
     *     as aliases.
     * @param string|null $alias Column alias name. Used only if the first argument is not an array.
     * @return self Itself
     */
    public function select($columns, string $alias = null): self
    {
        if (!is_array($columns)) {
            if ($alias === null) {
                $columns = [$columns];
            } else {
                $columns = [$alias => $columns];
            }
        }

        foreach ($columns as $alias => $column) {
            $column = $this->checkStringValue('Argument $columns['.$alias.']', $column);
            if (is_string($column)) {
                $column = $this->addTablePrefixToColumn($column);
            }
            if (is_string($alias)) {
                $this->select[$alias] = $column;
            } else {
                $this->select[] = $column;
            }
        }

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
     * Makes a self copy with dependencies and without query properties.
     *
     * @return Query
     */
    public function makeEmptyCopy(): self
    {
        return new static($this->tablePrefix);
    }

    /**
     * Check that value is suitable for being a "string or subquery" property of a query. Retrieves the callable
     * subquery.
     *
     * @param string $name Value name
     * @param string|callable|Query|StatementInterface $value
     * @return string|Query|StatementInterface
     * @throws InvalidQueryException
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
            return $this->retrieveCallableSubQuery($value);
        }

        return $value;
    }

    /**
     * Check that value is suitable for being a "int or null or subquery" property of a query. Retrieves the callable
     * subquery.
     *
     * @param string $name Value name
     * @param int|callable|Query|StatementInterface|null $value
     * @return int|Query|StatementInterface|null
     * @throws InvalidQueryException
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
            return $this->retrieveCallableSubQuery($value);
        }

        return $value;
    }

    /**
     * Retrieves the subquery from a callable.
     *
     * @param callable $callback
     * @return Query
     */
    protected function retrieveCallableSubQuery(callable $callback): self
    {
        $emptyQuery = $this->makeEmptyCopy();
        $result = $callback($emptyQuery);

        if ($result instanceof self) {
            return $result;
        } else {
            return $emptyQuery;
        }
    }
}
