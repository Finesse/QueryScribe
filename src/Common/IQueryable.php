<?php

namespace Finesse\QueryScribe\Common;

/**
 * An object that can give SQL text and bindings to execute a database query.
 *
 * @author Surgie
 */
interface IQueryable
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
