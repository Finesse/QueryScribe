<?php

namespace Finesse\QueryScribe\Grammars;

use Finesse\QueryScribe\Exceptions\InvalidQueryException;
use Finesse\QueryScribe\QueryBricks\Criteria\BetweenCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ColumnsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\CriteriaCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ExistsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\InCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\NullCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\RawCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ValueCriterion;
use Finesse\QueryScribe\QueryBricks\Criterion;

/**
 * A set of methods to implement criteria in the common grammar
 *
 * @author Surgie
 */
trait CommonGrammarCriteriaTrait
{
    /**
     * Converts an array of criteria (logical rules for WHERE, HAVING, etc.) to an SQL query text.
     *
     * @param Criterion[] $criteria List of criteria
     * @param array $bindings Bound values (array is filled by reference)
     * @return string SQL text or an empty string
     * @throws InvalidQueryException
     */
    protected function compileCriteria(array $criteria, array &$bindings): string
    {
        $criteriaSQL = '';
        $previousAppendRule = null;

        foreach ($criteria as $criterion) {
            $criterionSQL = $this->compileCriterion($criterion, $bindings);
            if ($criterionSQL === '') {
                continue;
            }

            $appendRule = $criterion->appendRule;

            if ($previousAppendRule === null) {
                $criteriaSQL .= $criterionSQL;
            } else {
                if ($appendRule === 'AND' && $previousAppendRule !== 'AND') {
                    $criteriaSQL = '('.$criteriaSQL.') '.$appendRule.' '.$criterionSQL;
                } else {
                    $criteriaSQL .= ' '.$appendRule.' '.$criterionSQL;
                }
            }

            $previousAppendRule = $appendRule;
        }

        return $criteriaSQL;
    }

    /**
     * Converts a single criterion to an SQL query text.
     *
     * @param Criterion $criterion Criterion
     * @param array $bindings Bound values (array is filled by reference)
     * @return string SQL text or an empty string
     * @throws InvalidQueryException
     */
    protected function compileCriterion(Criterion $criterion, array &$bindings): string
    {
        if ($criterion instanceof ValueCriterion) {
            return $this->compileValueCriterion($criterion, $bindings);
        }
        if ($criterion instanceof ColumnsCriterion) {
            return $this->compileColumnCriterion($criterion, $bindings);
        }
        if ($criterion instanceof BetweenCriterion) {
            return $this->compileBetweenCriterion($criterion, $bindings);
        }
        if ($criterion instanceof CriteriaCriterion) {
            return $this->compileCriteriaCriterion($criterion, $bindings);
        }
        if ($criterion instanceof ExistsCriterion) {
            return $this->compileExistsCriterion($criterion, $bindings);
        }
        if ($criterion instanceof InCriterion) {
            return $this->compileInCriterion($criterion, $bindings);
        }
        if ($criterion instanceof NullCriterion) {
            return $this->compileNullCriterion($criterion, $bindings);
        }
        if ($criterion instanceof RawCriterion) {
            return $this->compileRawCriterion($criterion, $bindings);
        }
        throw new InvalidQueryException('The given criterion '.get_class($criterion).' is unknown');
    }

    /**
     * @see compileCriterion For parameters and return value description
     */
    protected function compileValueCriterion(ValueCriterion $criterion, array &$bindings): string
    {
        $sql = sprintf(
            '%s %s %s',
            $this->compileIdentifier($criterion->column, $bindings),
            $criterion->rule,
            $this->compileValue($criterion->value, $bindings)
        );

        if ($criterion->rule === 'LIKE' && is_string($criterion->value)) {
            $sql .= ' ESCAPE ?';
            $this->mergeBindings($bindings, ['\\']);
        }

        return $sql;
    }

    /**
     * @see compileCriterion For parameters and return value description
     */
    protected function compileColumnCriterion(ColumnsCriterion $criterion, array &$bindings): string
    {
        return sprintf(
            '%s %s %s',
            $this->compileIdentifier($criterion->column1, $bindings),
            $criterion->rule,
            $this->compileIdentifier($criterion->column2, $bindings)
        );
    }

    /**
     * @see compileCriterion For parameters and return value description
     */
    protected function compileBetweenCriterion(BetweenCriterion $criterion, array &$bindings): string
    {
        return sprintf(
            '(%s %sBETWEEN %s AND %s)',
            $this->compileIdentifier($criterion->column, $bindings),
            $criterion->not ? 'NOT ' : '',
            $this->compileValue($criterion->min, $bindings),
            $this->compileValue($criterion->max, $bindings)
        );
    }

    /**
     * @see compileCriterion For parameters and return value description
     */
    protected function compileCriteriaCriterion(CriteriaCriterion $criterion, array &$bindings): string
    {
        $groupBindings = [];
        $groupSQL = $this->compileCriteria($criterion->criteria, $groupBindings);

        if ($groupSQL === '') {
            return '';
        } else {
            $this->mergeBindings($bindings, $groupBindings);

            return sprintf(
                '%s(%s)',
                $criterion->not ? 'NOT ' : '',
                $groupSQL
            );
        }
    }

    /**
     * @see compileCriterion For parameters and return value description
     */
    protected function compileExistsCriterion(ExistsCriterion $criterion, array &$bindings): string
    {
        return sprintf(
            '%sEXISTS %s',
            $criterion->not ? 'NOT ' : '',
            $this->compileSubQuery($criterion->subQuery, $bindings)
        );
    }

    /**
     * @see compileCriterion For parameters and return value description
     */
    protected function compileInCriterion(InCriterion $criterion, array &$bindings): string
    {
        if (is_array($criterion->values)) {
            if (empty($criterion->values)) {
                return $this->compileEmptyInCriterion($criterion, $bindings);
            }

            $subQuery = '';
            foreach ($criterion->values as $value) {
                $subQuery .= ($subQuery ? ', ' : '(') . $this->compileValue($value, $bindings);
            }
            $subQuery .= ')';
        } else {
            $subQuery = $this->compileSubQuery($criterion->values, $bindings);
        }

        return sprintf(
            '%s %sIN %s',
            $this->compileIdentifier($criterion->column, $bindings),
            $criterion->not ? 'NOT ' : '',
            $subQuery
        );
    }

    /**
     * Converts an IN criterion with zero values to an SQL query text. Some SQL dialects don't support `IN ()`.
     *
     * @see compileCriterion For parameters and return value description
     */
    protected function compileEmptyInCriterion(InCriterion $criterion, array &$bindings): string
    {
        // "In empty set" is always false, "not in empty set" is always true
        $this->mergeBindings($bindings, [$criterion->not ? 1 : 0]);
        return '?';
    }

    /**
     * @see compileCriterion For parameters and return value description
     */
    protected function compileNullCriterion(NullCriterion $criterion, array &$bindings): string
    {
        return sprintf(
            '%s IS %sNULL',
            $this->compileIdentifier($criterion->column, $bindings),
            $criterion->isNull ? '' : 'NOT '
        );
    }

    /**
     * @see compileCriterion For parameters and return value description
     */
    protected function compileRawCriterion(RawCriterion $criterion, array &$bindings): string
    {
        return $this->compileSubQuery($criterion->raw, $bindings);
    }
}
