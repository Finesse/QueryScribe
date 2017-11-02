<?php

namespace Finesse\QueryScribe\QueryBricks;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\StatementInterface;

/**
 * Contains properties and methods that add a possibility to use the SELECT section in a query.
 *
 * @author Surgie
 */
trait SelectTrait
{
    /**
     * @var string[]|Aggregate[]|Query[]|StatementInterface[] Columns names to select (prefixed). The string indexes are
     *     the aliases names. If no columns are provided, all columns should be selected.
     */
    public $select = [];

    /**
     * Table method alias
     *
     * @see Query::table For reference
     *
     * @param string|\Closure|Query|StatementInterface $table
     * @param string|null $alias
     * @return self
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function from($table, string $alias = null): self
    {
        return $this->table($table, $alias);
    }

    /**
     * Adds column or columns to the SELECT section.
     *
     * @param string|\Closure|Query|StatementInterface|string[]|\Closure[]|Query[]|StatementInterface[] $columns Columns
     *     to add. If string or raw, one column is added. If array, many columns are added and string indexes are
     *     treated as aliases.
     * @param string|null $alias Column alias name. Used only if the first argument is not an array.
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function addSelect($columns, string $alias = null): self
    {
        if (!is_array($columns)) {
            if ($alias === null) {
                $columns = [$columns];
            } else {
                $columns = [$alias => $columns];
            }
        }

        foreach ($columns as $alias => $column) {
            $column = $this->checkAndPrepareColumn('Argument $columns['.$alias.']', $column);

            if (is_string($alias)) {
                $this->select[$alias] = $column;
            } else {
                $this->select[] = $column;
            }
        }

        return $this;
    }

    /**
     * Adds a COUNT aggregate to the SELECT section.
     *
     * @param string|\Closure|Query|StatementInterface $column Column to count
     * @param string|null $alias Aggregate alias name
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function addCount($column = '*', string $alias = null): self
    {
        return $this->addAggregate('COUNT', $column, $alias);
    }

    /**
     * Adds a AVG aggregate to the SELECT section.
     *
     * @param string|\Closure|Query|StatementInterface $column Column to get average
     * @param string|null $alias Aggregate alias name
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function addAvg($column, string $alias = null): self
    {
        return $this->addAggregate('AVG', $column, $alias);
    }

    /**
     * Adds a SUM aggregate to the SELECT section.
     *
     * @param string|\Closure|Query|StatementInterface $column Column to get sum
     * @param string|null $alias Aggregate alias name
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function addSum($column, string $alias = null): self
    {
        return $this->addAggregate('SUM', $column, $alias);
    }

    /**
     * Adds a MIN aggregate to the SELECT section.
     *
     * @param string|\Closure|Query|StatementInterface $column Column to get min
     * @param string|null $alias Aggregate alias name
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function addMin($column, string $alias = null): self
    {
        return $this->addAggregate('MIN', $column, $alias);
    }

    /**
     * Adds a MAX aggregate to the SELECT section.
     *
     * @param string|\Closure|Query|StatementInterface $column Column to get max
     * @param string|null $alias Aggregate alias name
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function addMax($column, string $alias = null): self
    {
        return $this->addAggregate('MAX', $column, $alias);
    }

    /**
     * Adds an arbitrary aggregate to the SELECT section.
     *
     * @param string $function Aggregate function name
     * @param string|\Closure|Query|StatementInterface $column Column to count (not prefixed)
     * @param string|null $alias Aggregate alias name
     * @return self Itself
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    protected function addAggregate(string $function, $column, string $alias = null): self
    {
        $column = $this->checkAndPrepareColumn('Argument $column', $column);

        $aggregate = new Aggregate($function, $column);
        if ($alias === null) {
            $this->select[] = $aggregate;
        } else {
            $this->select[$alias] = $aggregate;
        }

        return $this;
    }
}
