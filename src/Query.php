<?php

namespace Finesse\QueryScribe;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\QueryBricks\ResolvesClosuresTrait;
use Finesse\QueryScribe\QueryBricks\InsertTrait;
use Finesse\QueryScribe\QueryBricks\Order;
use Finesse\QueryScribe\QueryBricks\SelectTrait;
use Finesse\QueryScribe\QueryBricks\WhereTrait;

/**
 * Represents a built query. It contains only a basic query data, not a SQL text. All the stored identifiers a final
 * (prefixed). It must not compile any SQL.
 *
 * All the Closures mentioned here as a value type are the function of the following type (if other is not specified):
 *  - Takes an empty query the first argument;
 *  - Returns a Query or a HasQueryInterface object or modifies the given object by link.
 *
 * The Closure is used instead of callable to prevent ambiguities when a string column name or a value may be treated as
 * a function name.
 *
 * Future features:
 *  * todo join
 *  * todo union
 *  * todo group by and having
 *  * todo distinct
 *
 * @author Surgie
 */
class Query
{
    use AddTablePrefixTrait, MakeRawTrait;
    use SelectTrait, InsertTrait, WhereTrait, ResolvesClosuresTrait;

    /**
     * @var string|self|StatementInterface|null Query target table name (prefixed)
     */
    public $table = null;

    /**
     * @var string|null Target table alias
     */
    public $tableAlias = null;

    /**
     * @var mixed[]|self[]|StatementInterface[] Fields to update. The indexes are the columns names, the
     *     values are the values.
     */
    public $update = [];

    /**
     * @var bool Should rows be deleted?
     */
    public $delete = false;

    /**
     * @var Order[]|string[] Orders. String value `random` means that the order should be random.
     */
    public $order = [];

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
     * @param string|\Closure|self|StatementInterface $table Not prefixed table name without quotes
     * @param string|null $alias Table alias
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function table($table, string $alias = null): self
    {
        $table = $this->checkStringValue('Argument $table', $table);

        $this->table = is_string($table) ? $this->addTablePrefix($table) : $table;
        $this->tableAlias = $alias;
        return $this;
    }

    /**
     * Adds values that should be updated
     *
     * @param mixed[]|\Closure[]|self[]|StatementInterface[] $values Fields to update. The indexes are the columns
     *     names, the values are the values.
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function addUpdate(array $values): self
    {
        foreach ($values as $column => $value) {
            if (!is_string($column)) {
                throw InvalidArgumentException::create('Argument $values indexes', $column, ['string']);
            }

            $value = $this->checkScalarOrNullValue('Argument $values['.$column.']', $value);
            $column = $this->addTablePrefixToColumn($column);
            $this->update[$column] = $value;
        }

        return $this;
    }

    /**
     * Makes the target rows be deleted.
     *
     * @return self Itself
     */
    public function setDelete(): self
    {
        $this->delete = true;
        return $this;
    }

    /**
     * Adds an order to the orders list.
     *
     * @param string|\Closure|self|StatementInterface $column Column to order by
     * @param string $direction Order direction: `asc` - ascending, `desc` - descending
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orderBy($column, string $direction = 'asc'): self
    {
        $column = $this->checkAndPrepareColumn('Argument $column', $column);
        $this->order[] = new Order($column, strtolower($direction) === 'desc');
        return $this;
    }

    /**
     * Adds a random order to the orders list.
     *
     * @return self Itself
     */
    public function inRandomOrder(): self
    {
        $this->order[] = 'random';
        return $this;
    }

    /**
     * Sets the offset.
     *
     * @param int|\Closure|self|StatementInterface|null $offset Offset. Null removes the offset.
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function offset($offset): self
    {
        $this->offset = $this->checkIntOrNullValue('Argument $offset', $offset);
        return $this;
    }

    /**
     * Sets the limit.
     *
     * @param int|\Closure|self|StatementInterface|null $limit Limit. Null removes the limit.
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function limit($limit): self
    {
        $this->limit = $this->checkIntOrNullValue('Argument $limit', $limit);
        return $this;
    }

    /**
     * Makes an empty self copy with dependencies.
     *
     * @return self
     */
    public function makeEmptyCopy(): self
    {
        return new static($this->tablePrefix);
    }

    /**
     * Makes a self copy with dependencies for passing to a subquery callback.
     *
     * @return self
     */
    public function makeCopyForSubQuery(): self
    {
        return $this->makeEmptyCopy();
    }

    /**
     * Makes a self copy with dependencies for passing to a criteria group callback.
     *
     * @return self
     */
    public function makeCopyForCriteriaGroup(): self
    {
        $query = $this->makeEmptyCopy();

        // The `table` method is not used because it adds extra prefix
        $query->table = $this->table;
        $query->tableAlias = $this->tableAlias;

        return $query;
    }

    /**
     * Check that value is suitable for being a "string or subquery" property of a query. Retrieves the closure
     * subquery.
     *
     * @param string $name Value name
     * @param string|\Closure|self|StatementInterface $value
     * @return string|self|StatementInterface
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    protected function checkStringValue(string $name, $value)
    {
        if (
            !is_string($value) &&
            !($value instanceof \Closure) &&
            !($value instanceof self) &&
            !($value instanceof StatementInterface)
        ) {
            throw InvalidArgumentException::create(
                $name,
                $value,
                ['string', \Closure::class, self::class, StatementInterface::class]
            );
        }

        if ($value instanceof \Closure) {
            return $this->resolveSubQueryClosure($value);
        }

        return $value;
    }

    /**
     * Check that value is suitable for being a "int or null or subquery" property of a query. Retrieves the closure
     * subquery.
     *
     * @param string $name Value name
     * @param int|\Closure|self|StatementInterface|null $value
     * @return int|self|StatementInterface|null
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    protected function checkIntOrNullValue(string $name, $value)
    {
        if (
            $value !== null &&
            !is_numeric($value) &&
            !($value instanceof \Closure) &&
            !($value instanceof self) &&
            !($value instanceof StatementInterface)
        ) {
            throw InvalidArgumentException::create(
                $name,
                $value,
                ['integer', \Closure::class, self::class, StatementInterface::class, 'null']
            );
        }

        if (is_numeric($value)) {
            $value = (int)$value;
        } elseif ($value instanceof \Closure) {
            return $this->resolveSubQueryClosure($value);
        }

        return $value;
    }

    /**
     * Check that value is suitable for being a "scalar or null or subquery" property of a query. Retrieves the closure
     * subquery.
     *
     * @param string $name Value name
     * @param mixed|\Closure|self|StatementInterface|null $value
     * @return mixed|self|StatementInterface|null
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    protected function checkScalarOrNullValue(string $name, $value)
    {
        if (
            $value !== null &&
            !is_scalar($value) &&
            !($value instanceof \Closure) &&
            !($value instanceof self) &&
            !($value instanceof StatementInterface)
        ) {
            throw InvalidArgumentException::create(
                $name,
                $value,
                ['scalar', \Closure::class, self::class, StatementInterface::class, 'null']
            );
        }

        if ($value instanceof \Closure) {
            return $this->resolveSubQueryClosure($value);
        }

        return $value;
    }

    /**
     * Check that value is suitable for being a subquery property of a query. Retrieves the closure subquery.
     *
     * @param string $name Value name
     * @param \Closure|self|StatementInterface $value
     * @return self|StatementInterface
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    protected function checkSubQueryValue(string $name, $value)
    {
        if (
            !($value instanceof \Closure) &&
            !($value instanceof self) &&
            !($value instanceof StatementInterface)
        ) {
            throw InvalidArgumentException::create(
                $name,
                $value,
                [\Closure::class, self::class, StatementInterface::class]
            );
        }

        if ($value instanceof \Closure) {
            $value = $this->resolveSubQueryClosure($value);
        }

        return $value;
    }

    /**
     * Check that value is suitable for being a column name of a query. Retrieves the closure subquery. Adds a table
     * prefix to a column name (if it contains a table name).
     *
     * @param string $name Value name
     * @param string|\Closure|self|StatementInterface $column
     * @return string|self|StatementInterface
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    protected function checkAndPrepareColumn(string $name, $column)
    {
        $column = $this->checkStringValue($name, $column);
        if (is_string($column)) {
            $column = $this->addTablePrefixToColumn($column);
        }
        return $column;
    }
}
