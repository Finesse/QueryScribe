<?php

namespace Finesse\QueryScribe\QueryBricks;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\QueryBricks\Criteria\BetweenCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ColumnsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\CriteriaCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ExistsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\InCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\NullCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\RawCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ValueCriterion;
use Finesse\QueryScribe\Raw;
use Finesse\QueryScribe\StatementInterface;

/**
 * Contains properties and methods that add a possibility to use the WHERE section in a query.
 *
 * @author Surgie
 */
trait WhereTrait
{
    /**
     * @var Criterion[] Where criteria
     */
    public $where = [];

    /**
     * Adds a criterion to the WHERE section. Possible formats:
     *  * column, rule, value — column compared to value by the given rule;
     *  * column, value — column is equal to value;
     *  * Closure – grouped criteria;
     *  * array[] – criteria joined by the AND rule (the values are the arguments lists for this method);
     *  * Raw – raw SQL.
     *
     * @param string|\Closure|Query|StatementInterface|array[] $column
     * @param string|mixed|\Closure|Query|StatementInterface|null $rule
     * @param mixed|\Closure|Query|StatementInterface|null $value
     * @param int $appendRul eHow the criterion should be appended to the others (on of Criterion::APPEND_RULE_*
     *    constants)
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function where($column, $rule = null, $value = null, int $appendRule = Criterion::APPEND_RULE_AND): self
    {
        if ($rule === null && $value === null) {
            if ($column instanceof \Closure) {
                $groupQuery = $this->resolveCriteriaGroupClosure($column);
                $this->where[] = new CriteriaCriterion($groupQuery->where, false, $appendRule);
                return $this;
            }

            if (is_array($column)) {
                return $this->where(
                    function (self $query) use ($column) {
                        foreach ($column as $criterionData) {
                            $query = $query->where(...$criterionData);
                        }
                        return $query;
                    },
                    null,
                    null,
                    $appendRule
                );
            }

            if ($column instanceof StatementInterface) {
                $this->where[] = new RawCriterion($column, $appendRule);
                return $this;
            }

            return $this->handleException(new InvalidArgumentException(sprintf(
                'The following argument list is not supported: %s, null and null',
                gettype($column)
            )));
        }

        if ($value === null) {
            $value = $rule;
            $rule = '=';
        }

        if (!is_string($rule)) {
            return $this->handleException(InvalidArgumentException::create('Argument $rule', $rule, ['string']));
        }

        $rule = strtoupper($rule);

        $column = $this->checkStringValue('Argument $column', $column);
        $value = $this->checkScalarOrNullValue('Argument $value', $value);
        $this->where[] = new ValueCriterion($column, $rule, $value, $appendRule);
        return $this;
    }

    /**
     * Does the same as the `where` method but with the OR append rule. See the readme for more info about append
     * rules.
     *
     * @see WhereTrait::where
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orWhere($column, $rule = null, $value = null): self
    {
        return $this->where($column, $rule, $value, Criterion::APPEND_RULE_OR);
    }

    /**
     * Adds a group of criteria wrapped by NOT.
     *
     * @param \Closure $callback Makes a group of criteria
     * @param int $appendRul eHow the criterion should be appended to the others (on of Criterion::APPEND_RULE_*
     *    constants)
     * @return $this
     * @throws InvalidReturnValueException
     */
    public function whereNot(\Closure $callback, int $appendRule = Criterion::APPEND_RULE_AND): self
    {
        $groupQuery = $this->resolveCriteriaGroupClosure($callback);
        $this->where[] = new CriteriaCriterion($groupQuery->where, true, $appendRule);
        return $this;
    }

    /**
     * Adds a group of criteria wrapped by NOT with the OR append rule. See the readme for more info about append
     * rules.
     *
     * @param \Closure $callback Makes a group of criteria
     * @return $this
     */
    public function orWhereNot(\Closure $callback): self
    {
        return $this->whereNot($callback, Criterion::APPEND_RULE_OR);
    }

    /**
     * Adds a raw SQL criterion to the WHERE section.
     *
     * @param string $query SQL statement
     * @param array $bindings Values to bind to the statement
     * @param int $appendRule How the criterion should be appended to the others (on of Criterion::APPEND_RULE_*
     *    constants)
     * @return $this
     */
    public function whereRaw(string $query, array $bindings = [], int $appendRule = Criterion::APPEND_RULE_AND): self
    {
        return $this->where(new Raw($query, $bindings), null, null, $appendRule);
    }

    /**
     * Adds a raw SQL criterion to the WHERE section with the OR append rule. See the readme for more info about append
     * rules.
     *
     * @param string $query SQL statement
     * @param array $bindings Values to bind to the statement
     * @return $this
     */
    public function orWhereRaw(string $query, array $bindings = []): self
    {
        return $this->whereRaw($query, $bindings, Criterion::APPEND_RULE_OR);
    }

    /**
     * Adds a BETWEEN criterion to the WHERE section.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @param mixed|\Closure|Query|StatementInterface $min Left value
     * @param mixed|\Closure|Query|StatementInterface $max Right value
     * @param bool $not Whether the rule should be NOT BETWEEN
     * @param int $appendRule How the criterion should be appended to the others (on of Criterion::APPEND_RULE_*
     *    constants)
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function whereBetween(
        $column,
        $min,
        $max,
        bool $not = false,
        int $appendRule = Criterion::APPEND_RULE_AND
    ): self {
        $column = $this->checkStringValue('Argument $column', $column);
        $min = $this->checkScalarOrNullValue('The left between value', $min);
        $max = $this->checkScalarOrNullValue('The right between value', $max);

        $this->where[] = new BetweenCriterion($column, $min, $max, $not, $appendRule);
        return $this;
    }

    /**
     * Adds a BETWEEN criterion to the WHERE section with the OR append rule. See the readme for more info about append
     * rules.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @param mixed|\Closure|Query|StatementInterface $min Left value
     * @param mixed|\Closure|Query|StatementInterface $max Right value
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orWhereBetween($column, $min, $max): self
    {
        return $this->whereBetween($column, $min, $max, false, Criterion::APPEND_RULE_OR);
    }

    /**
     * Adds a NOT BETWEEN criterion to the WHERE section.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @param mixed|\Closure|Query|StatementInterface $min Left value
     * @param mixed|\Closure|Query|StatementInterface $max Right value
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function whereNotBetween($column, $min, $max): self
    {
        return $this->whereBetween($column, $min, $max, true);
    }

    /**
     * Adds a NOT BETWEEN criterion to the WHERE section with the OR append rule. See the readme for more info about
     * append rules.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @param mixed|\Closure|Query|StatementInterface $min Left value
     * @param mixed|\Closure|Query|StatementInterface $max Right value
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orWhereNotBetween($column, $min, $max): self
    {
        return $this->whereBetween($column, $min, $max, true, Criterion::APPEND_RULE_OR);
    }

    /**
     * Adds a IN criterion to the WHERE section.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @param mixed[]\Closure[]|Query[]|StatementInterface[]|\Closure|Query|StatementInterface Haystack values
     * @param bool $not Whether the rule should be NOT IN
     * @param int $appendRule How the criterion should be appended to the others (on of Criterion::APPEND_RULE_*
     *    constants)
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function whereIn($column, $values, bool $not = false, int $appendRule = Criterion::APPEND_RULE_AND): self
    {
        $column = $this->checkStringValue('Argument $column', $column);

        if (
            !is_array($values) &&
            !($values instanceof \Closure) &&
            !($values instanceof Query) &&
            !($values instanceof StatementInterface)
        ) {
            return $this->handleException(InvalidArgumentException::create(
                'The IN value',
                $values,
                ['array', \Closure::class, Query::class, StatementInterface::class, 'null']
            ));
        }

        if (is_array($values)) {
            foreach ($values as $index => &$value) {
                $value = $this->checkScalarOrNullValue('Argument $values['.$index.']', $value);
            }
        } elseif ($values instanceof \Closure) {
            $values = $this->resolveSubQueryClosure($values);
        }

        $this->where[] = new InCriterion($column, $values, $not, $appendRule);
        return $this;
    }

    /**
     * Adds a IN criterion to the WHERE section with the OR append rule. See the readme for more info about append
     * rules.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @param mixed[]|\Closure|Query|StatementInterface Haystack values
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orWhereIn($column, $values): self
    {
        return $this->whereIn($column, $values, false, Criterion::APPEND_RULE_OR);
    }

    /**
     * Adds a NOT IN criterion to the WHERE section.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @param mixed[]|\Closure|Query|StatementInterface Haystack values
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function whereNotIn($column, $values): self
    {
        return $this->whereIn($column, $values, true);
    }

    /**
     * Adds a NOT IN criterion to the WHERE section with the OR append rule. See the readme for more info about append
     * rules.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @param mixed[]|\Closure|Query|StatementInterface Haystack values
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orWhereNotIn($column, $values): self
    {
        return $this->whereIn($column, $values, true, Criterion::APPEND_RULE_OR);
    }

    /**
     * Adds a IS NULL criterion to the WHERE section.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @param bool $not Whether the rule should be NOT NULL
     * @param int $appendRule How the criterion should be appended to the others (on of Criterion::APPEND_RULE_*
     *    constants)
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function whereNull($column, bool $not = false, int $appendRule = Criterion::APPEND_RULE_AND): self
    {
        $column = $this->checkStringValue('Argument $column', $column);

        $this->where[] = new NullCriterion($column, !$not, $appendRule);
        return $this;
    }

    /**
     * Adds a IS NULL criterion to the WHERE section with the OR append rule. See the readme for more info about append
     * rules.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orWhereNull($column): self
    {
        return $this->whereNull($column, false, Criterion::APPEND_RULE_OR);
    }

    /**
     * Adds a IS NOT NULL criterion to the WHERE section.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function whereNotNull($column): self
    {
        return $this->whereNull($column, true);
    }

    /**
     * Adds a IS NOT NULL criterion to the WHERE section with the OR append rule. See the readme for more info about
     * append rules.
     *
     * @param string|\Closure|Query|StatementInterface $column Target column
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orWhereNotNull($column): self
    {
        return $this->whereNull($column, true, Criterion::APPEND_RULE_OR);
    }

    /**
     * Adds a columns comparing rule to the WHERE section. You may either pass two columns and rule or only two columns:
     * `whereColumn($column1, $column2)`, in this case the rule is `=`. Or you may pass an array of compares as the
     * first argument (they are appended with the AND rule).
     *
     * @param string|\Closure|Query|StatementInterface|array[] $column Target column 1
     * @param string|\Closure|Query|StatementInterface|null $rule Rule or target column 2
     * @param string|\Closure|Query|StatementInterface|null $column Target column 2 or nothing
     * @param int $appendRule How the criterion should be appended to the others (on of Criterion::APPEND_RULE_*
     *    constants)
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function whereColumn($column1, $rule = null, $column2 = null, int $appendRule = Criterion::APPEND_RULE_AND): self
    {
        if (is_array($column1)) {
            return $this->where(
                function (self $query) use ($column1) {
                    foreach ($column1 as $criterionData) {
                        $query = $query->whereColumn(...$criterionData);
                    }
                    return $query;
                },
                null,
                null,
                $appendRule
            );
        }

        if ($column2 === null) {
            $column2 = $rule;
            $rule = '=';
        }

        if (!is_string($rule)) {
            return $this->handleException(InvalidArgumentException::create('Argument $rule', $rule, ['string']));
        }

        $rule = strtoupper($rule);
        $column1 = $this->checkStringValue('Argument $column1', $column1);
        $column2 = $this->checkStringValue('Argument $column2', $column2);

        $this->where[] = new ColumnsCriterion($column1, $rule, $column2, $appendRule);
        return $this;
    }

    /**
     * Adds a columns comparing rule to the WHERE section with the OR append rule. You may either pass two columns and
     * rule or only two columns: `whereColumn($column1, $column2)`, in this case the rule is `=`. Or you may pass an
     * array of compares as the first argument (they are appended with the AND rule). See the readme for more info about
     * append rules.
     *
     * @param string|\Closure|Query|StatementInterface|array[] $column Target column 1
     * @param string|\Closure|Query|StatementInterface $rule Rule or target column 2
     * @param string|\Closure|Query|StatementInterface|null $column Target column 2 or nothing
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orWhereColumn($column1, $rule, $column2 = null): self
    {
        return $this->whereColumn($column1, $rule, $column2, Criterion::APPEND_RULE_OR);
    }

    /**
     * Adds a EXISTS criterion to the WHERE section.
     *
     * @param $subQuery \Closure|Query|StatementInterface Query to place inside the EXISTS clause. If closure, it
     *    should create the query.
     * @param bool $not Whether the rule should be NOT EXISTS
     * @param int $appendRule How the criterion should be appended to the others (on of Criterion::APPEND_RULE_*
     *    constants)
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function whereExists($subQuery, bool $not = false, int $appendRule = Criterion::APPEND_RULE_AND): self
    {
        $subQuery = $this->checkSubQueryValue('Argument $subQuery', $subQuery);

        $this->where[] = new ExistsCriterion($subQuery, $not, $appendRule);
        return $this;
    }

    /**
     * Adds a EXISTS criterion to the WHERE section with the OR append rule. See the readme for more info about append
     * rules.
     *
     * @param $subQuery \Closure|Query|StatementInterface Query to place inside the EXISTS clause. If closure, it
     *    should create the query.
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orWhereExists($subQuery): self
    {
        return $this->whereExists($subQuery, false, Criterion::APPEND_RULE_OR);
    }

    /**
     * Adds a NOT EXISTS criterion to the WHERE section.
     *
     * @param $subQuery \Closure|Query|StatementInterface Query to place inside the EXISTS clause. If closure, it
     *    should create the query.
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function whereNotExists($subQuery): self
    {
        return $this->whereExists($subQuery, true);
    }

    /**
     * Adds a NOT EXISTS criterion to the WHERE section with the OR append rule. See the readme for more info about
     * append rules.
     *
     * @param $subQuery \Closure|Query|StatementInterface Query to place inside the EXISTS clause. If closure, it
     *    should create the query.
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function orWhereNotExists($subQuery): self
    {
        return $this->whereExists($subQuery, true, Criterion::APPEND_RULE_OR);
    }
}
