<?php

namespace Finesse\QueryScribe;

/**
 * Processes a built query.
 *
 * @author Surgie
 */
interface PostProcessorInterface
{
    /**
     * Processes a built query. MUST NOT change the given query or it's components by the link but may return it.
     *
     * @param Query $query
     * @return Query
     */
    public function process(Query $query): Query;
}
