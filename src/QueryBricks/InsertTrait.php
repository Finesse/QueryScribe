<?php

namespace Finesse\QueryScribe\QueryBricks;

use Finesse\QueryScribe\Exceptions\InvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidReturnValueException;
use Finesse\QueryScribe\Query;
use Finesse\QueryScribe\StatementInterface;

/**
 * Contains properties and methods that add a possibility to use the INSERT section in a query.
 *
 * @author Surgie
 */
trait InsertTrait
{
    /**
     * @var mixed[][]||Query[][]|StatementInterface[][]|InsertFromSelect[] Values to insert to the table. An array value
     *     is a list of rows to insert. Each row is an associative array where indexes are column names and values are
     *     cell values.
     */
    public $insert = [];

    /**
     * Adds a row to insert to the table.
     *
     * Warning, calling this method removes a given insert from select.
     *
     * @param mixed[][]|\Closure[][]|Query[][]|StatementInterface[][]|mixed[]|\Closure[]|Query[]|StatementInterface[] $rows
     *     A row or an array of rows. Each row is an associative array where indexes are column names and values are
     *     cell values. Rows indexes must be strings.
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function addInsert(array $rows): self
    {
        if (!empty($rows) && !is_array(reset($rows))) {
            $rows = [$rows];
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw InvalidArgumentException::create('Argument $rows['.$index.']', $row, ['array']);
            }

            $filteredRow = [];

            foreach ($row as $column => $value) {
                if (!is_string($column)) {
                    throw InvalidArgumentException::create('The argument $rows['.$index.'] indexes', $column, ['string']);
                }

                $value = $this->checkScalarOrNullValue('Argument $rows['.$index.']['.$column.']', $value);
                $filteredRow[$column] = $value;
            }

            $this->insert[] = $filteredRow;
        }

        return $this;
    }

    /**
     * Adds an instruction that the query should insert values to the table from the select query.
     *
     * Warning, calling this method removes all the rows set by the `insert` method.
     *
     * @param string[]|\Closure|self|StatementInterface $columns The list of the columns to which the selected values
     *     should be inserted. You may omit this argument and pass the $selectQuery argument instead.
     * @param \Closure|self|StatementInterface|null $selectQuery
     * @return $this
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     */
    public function addInsertFromSelect($columns, $selectQuery = null): self
    {
        if ($selectQuery === null) {
            $selectQuery = $columns;
            $columns = null;
        }

        if ($columns !== null) {
            if (!is_array($columns)) {
                throw InvalidArgumentException::create('Argument $columns', $columns, ['array', 'null']);
            }
            foreach ($columns as $index => $column) {
                if (!is_string($column)) {
                    throw InvalidArgumentException::create('Argument $columns['.$index.']', $column, ['string']);
                }
            }
        }

        $selectQuery = $this->checkSubQueryValue('Argument $selectQuery', $selectQuery);

        $this->insert[] = new InsertFromSelect($columns, $selectQuery);
        return $this;
    }
}
