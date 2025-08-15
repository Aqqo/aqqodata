<?php

namespace Aqqo\OData\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Interface for all filter objects
 */
interface FilterInterface
{
    /**
     * Apply the filter to the given query builder
     *
     * @param Builder $builder
     * @return void
     */
    public function apply(Builder $builder): void;

    /**
     * Get the string representation of the filter
     *
     * @return string
     */
    public function toString(): string;
}
