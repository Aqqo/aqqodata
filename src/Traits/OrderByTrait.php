<?php

namespace Aqqo\OData\Traits;

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
    */
    public function appendOrderBy(string $orderby, Builder $builder): void
    {
        foreach (explode(',', $orderby) as $order) {
            $order = explode(' ', trim($order));
            $field = $order[0];
            $direction = $order[1] ?? 'asc';

            // Map OData property name -> db column when known, otherwise keep old behavior.
            $mapped = $this->isPropertyOrderable($field, class_basename($builder->getModel()));
            if (is_string($mapped)) {
                $field = $mapped;
            }

            $builder->orderBy($field, $direction);
        }
    }
}

