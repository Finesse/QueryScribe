<?php

namespace Finesse\QueryScribe\QueryBricks;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criteria\CriteriaCriterion;
use Finesse\QueryScribe\StatementInterface;

/**
 * Contains properties and methods that add a possibility to use the JOIN sections in a query.
 *
 * @author Surgie
 */
trait JoinTrait
{
    /**
     * @var Join[] Joins
     * @todo Grammar
     * @todo Documentation
     */
    public $join = [];

    /**
     * An alias for `innerJoin`
     *
     * @see innerJoin The arguments format
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function join($table, ...$criterion): self
    {
        return $this->innerJoin($table, ...$criterion);
    }

    /**
     * Adds a table joined with the "inner" rule.
     *
     * The first argument is a table name or an array where the first value is a table name and the second value is the
     * table alias name.
     *
     * The rest arguments may have one of the following formats:
     *  - column1, rule, column1 — column compared to another column by the given rule. Include a table name to
     *    eliminate an ambiguity;
     *  - column1, column2 — column is equal to another column;
     *  - Closure – complex joining criterion;
     *  - array[] – criteria joined by the AND rule (the values are the arguments lists for this method);
     *  - Raw – raw SQL.
     *
     * @param string|\Closure|Query|StatementInterface|string[]|\Closure[]|self[]|StatementInterface[] $table
     * @param string|\Closure|Query|StatementInterface|array[] $column1
     * @param string|\Closure|Query|StatementInterface|null $rule
     * @param string|\Closure|Query|StatementInterface|null $column2
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function innerJoin($table, ...$criterion): self
    {
        $this->join[] = $this->joinArgumentsToJoinObject('INNER', $table, $criterion);
        return $this;
    }

    /**
     * Adds a table joined with the "outer" rule.
     *
     * @see innerJoin The arguments format
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function outerJoin($table, ...$criterion): self
    {
        $this->join[] = $this->joinArgumentsToJoinObject('OUTER', $table, $criterion);
        return $this;
    }

    /**
     * Adds a table joined with the "left" rule.
     *
     * @see innerJoin The arguments format
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function leftJoin($table, ...$criterion): self
    {
        $this->join[] = $this->joinArgumentsToJoinObject('LEFT', $table, $criterion);
        return $this;
    }

    /**
     * Adds a table joined with the "right" rule.
     *
     * @see innerJoin The arguments format
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function rightJoin($table, ...$criterion): self
    {
        $this->join[] = $this->joinArgumentsToJoinObject('RIGHT', $table, $criterion);
        return $this;
    }

    /**
     * Adds a table joined with the "right" rule.
     *
     * @see innerJoin The arguments format
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function crossJoin($table): self
    {
        $this->join[] = $this->joinArgumentsToJoinObject('CROSS', $table);
        return $this;
    }

    /**
     * An alias for `whereColumn`
     *
     * @see WhereTrait::whereColumn
     * @param string|\Closure|Query|StatementInterface|array[] $column1
     * @param string|\Closure|Query|StatementInterface|null $rule
     * @param string|\Closure|Query|StatementInterface|null $column2
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function on(...$arguments): self
    {
        return $this->whereColumn(...$arguments);
    }

    /**
     * An alias for `orWhereColumn`
     *
     * @see WhereTrait::orWhereColumn
     * @param string|\Closure|Query|StatementInterface|array[] $column1
     * @param string|\Closure|Query|StatementInterface|null $rule
     * @param string|\Closure|Query|StatementInterface|null $column2
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orOn(...$arguments): self
    {
        return $this->orWhereColumn(...$arguments);
    }

    /**
     * Converts `join` method arguments to a join object
     *
     * @see innerJoin The arguments format
     * @params string $type The join type (INNER, LEFT, etc.)
     * @params mixed $table The joined table
     * @params array The join criterion arguments
     * @return Join
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    protected function joinArgumentsToJoinObject(string $type, $table, array $criterion = []): Join
    {
        if (is_array($table)) {
            $tableName = $table[0] ?? null;
            $tableAlias = $table[1] ?? null;
        } else {
            $tableName = $table;
            $tableAlias = null;
        }

        $tableName = $this->checkStringValue('Argument $tableName', $tableName);
        if ($tableAlias !== null && !is_string($tableAlias)) {
            return $this->handleException(InvalidArgumentException::create(
                'Argument $tableAlias',
                $tableAlias,
                ['string', 'null']
            ));
        }

        if ($criterion) {
            $criterion = $this->whereArgumentsToCriterion($criterion, 'AND', true);
            $criteria = ($criterion instanceof CriteriaCriterion && !$criterion->not)
                ? $criterion->criteria
                : [$criterion];
        } else {
            $criteria = [];
        }

        return new Join($type, $tableName, $tableAlias, $criteria);
    }
}
