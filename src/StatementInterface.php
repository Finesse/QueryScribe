<?php

namespace Finesse\QueryScribe;

/**
 * An object that can give SQL text and bindings to execute a database query or to add to another query.
 *
 * @author Surgie
 */
interface StatementInterface
{
    /**
     * @return string SQL query text
     */
    public function getSQL(): string;

    /**
     * @return array Values to bind to the statement
     */
    public function getBindings(): array;
}
