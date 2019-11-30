<?php

namespace Finesse\QueryScribe\PostProcessors;

use Finesse\QueryScribe\QueryBricks\Criteria\BetweenCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ColumnsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\CriteriaCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ExistsCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\InCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\NullCriterion;
use Finesse\QueryScribe\QueryBricks\Criteria\ValueCriterion;
use Finesse\QueryScribe\QueryBricks\Criterion;

/**
 * A set of methods to implement criteria in the abstract processor
 *
 * @author Surgie
 */
trait AbstractProcessorCriteriaTrait
{
    /**
     * Processes a single criterion.
     *
     * @param Criterion $criterion
     * @param mixed $context The processing context
     * @return Criterion
     */
    protected function processCriterion(Criterion $criterion, $context): Criterion
    {
        if ($criterion instanceof ValueCriterion) {
            return $this->processValueCriterion($criterion, $context);
        }
        if ($criterion instanceof ColumnsCriterion) {
            return $this->processColumnCriterion($criterion, $context);
        }
        if ($criterion instanceof BetweenCriterion) {
            return $this->processBetweenCriterion($criterion, $context);
        }
        if ($criterion instanceof CriteriaCriterion) {
            return $this->processCriteriaCriterion($criterion, $context);
        }
        if ($criterion instanceof ExistsCriterion) {
            return $this->processExistsCriterion($criterion, $context);
        }
        if ($criterion instanceof InCriterion) {
            return $this->processInCriterion($criterion, $context);
        }
        if ($criterion instanceof NullCriterion) {
            return $this->processNullCriterion($criterion, $context);
        }
        return $criterion;
    }

    /**
     * @see processCriterion For parameters and return value description
     */
    protected function processValueCriterion(ValueCriterion $criterion, $context): ValueCriterion
    {
        $column = $this->processColumnOrSubQuery($criterion->column, $context);
        $value = $this->processValueOrSubQuery($criterion->value, $context);

        if ($column === $criterion->column && $value === $criterion->value) {
            return $criterion;
        } else {
            return new ValueCriterion($column, $criterion->rule, $value, $criterion->appendRule);
        }
    }

    /**
     * @see processCriterion For parameters and return value description
     */
    protected function processColumnCriterion(ColumnsCriterion $criterion, $context): ColumnsCriterion
    {
        $column1 = $this->processColumnOrSubQuery($criterion->column1, $context);
        $column2 = $this->processColumnOrSubQuery($criterion->column2, $context);

        if ($column1 === $criterion->column1 && $column2 === $criterion->column2) {
            return $criterion;
        } else {
            return new ColumnsCriterion($column1, $criterion->rule, $column2, $criterion->appendRule);
        }
    }

    /**
     * @see processCriterion For parameters and return value description
     */
    protected function processBetweenCriterion(BetweenCriterion $criterion, $context): BetweenCriterion
    {
        $column = $this->processColumnOrSubQuery($criterion->column, $context);
        $min = $this->processValueOrSubQuery($criterion->min, $context);
        $max = $this->processValueOrSubQuery($criterion->max, $context);

        if ($column === $criterion->column && $min === $criterion->min && $max === $criterion->max) {
            return $criterion;
        } else {
            return new BetweenCriterion($column, $min, $max, $criterion->not, $criterion->appendRule);
        }
    }

    /**
     * @see processCriterion For parameters and return value description
     */
    protected function processCriteriaCriterion(CriteriaCriterion $criterion, $context): CriteriaCriterion
    {
        $criteria = [];
        foreach ($criterion->criteria as $index => $subCriterion) {
            $criteria[$index] = $this->processCriterion($subCriterion, $context);
        }

        if ($criteria === $criterion->criteria) {
            return $criterion;
        } else {
            return new CriteriaCriterion($criteria, $criterion->not, $criterion->appendRule);
        }
    }

    /**
     * @see processCriterion For parameters and return value description
     */
    protected function processExistsCriterion(ExistsCriterion $criterion, $context): ExistsCriterion
    {
        $subQuery = $this->processSubQuery($criterion->subQuery, $context);

        if ($subQuery === $criterion->subQuery) {
            return $criterion;
        } else {
            return new ExistsCriterion($subQuery, $criterion->not, $criterion->appendRule);
        }
    }

    /**
     * @see processCriterion For parameters and return value description
     */
    protected function processInCriterion(InCriterion $criterion, $context): InCriterion
    {
        $column = $this->processColumnOrSubQuery($criterion->column, $context);
        if (is_array($criterion->values)) {
            $values = [];
            foreach ($criterion->values as $index => $value) {
                $values[$index] = $this->processValueOrSubQuery($value, $context);
            }
        } else {
            $values = $this->processSubQuery($criterion->values, $context);
        }

        if ($column === $criterion->column && $values === $criterion->values) {
            return $criterion;
        } else {
            return new InCriterion($column, $values, $criterion->not, $criterion->appendRule);
        }
    }

    /**
     * @see processCriterion For parameters and return value description
     */
    protected function processNullCriterion(NullCriterion $criterion, $context): NullCriterion
    {
        $column = $this->processColumnOrSubQuery($criterion->column, $context);

        if ($column === $criterion->column) {
            return $criterion;
        } else {
            return new NullCriterion($column, $criterion->isNull, $criterion->appendRule);
        }
    }
}
