<?php

namespace Aqqo\OData\Traits;

use Aqqo\OData\Exceptions\QueryException;
use Aqqo\OData\Utils\ClassUtils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModelClass of Model
 */
trait OrderByTrait
{
    /**
     * Apply the $orderby query parameter to the response.
     *
     * @return void
     */
    public function addOrderBy(): void
    {
        $orderby = $this->request?->input('$orderby');

        if ($orderby) {
            $this->appendOrderBy($orderby, $this->subject);
        }
    }

    /**
    * @param string $orderby
    * @param Builder<TModelClass> $builder
    *
    * @return void
    * @throws QueryException
    */
    public function appendOrderBy(string $orderby, Builder $builder): void
    {
        foreach (explode(',', $orderby) as $order) {
            $order = explode(' ', trim($order));
            $property = $order[0];
            $direction = strtolower($order[1] ?? 'asc');

            if (!in_array($direction, ['asc', 'desc'], true)) {
                if ($this->strict) {
                    throw new QueryException("Cannot order by '{$property} {$direction}'. Direction must be 'asc' or 'desc'.");
                }

                $direction = 'asc';
            }

            // Map the OData property alias to its database column, exactly like $filter does.
            // Non-strict keeps the historic raw passthrough for undeclared properties.
            $column = $this->isPropertyOrderable($property, ClassUtils::getShortName($builder->getModel()));

            if (!is_string($column) && $this->strict) {
                throw new QueryException("Cannot order by '{$property}'. Property is unknown or not orderable.");
            }

            $builder->orderBy(is_string($column) ? $column : $property, $direction);
        }
    }
}
