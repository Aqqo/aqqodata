<?php

namespace Aqqo\OData\Traits;

use Aqqo\OData\Utils\StringUtils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionException;

/**
 * @template TModelClass of Model
 * @template TRelatedModel of Model
 */
trait ExpandTrait
{
    /**
     * Apply $expand parameters from the request to the Eloquent query builder.
     *
     * @return void
     * @throws ReflectionException
     */
    public function addExpands(): void
    {
        $expandQuery = $this->request?->input('$expand');

        if (empty($expandQuery)) {
            return;
        }

        // Split the expand query into individual expand expressions
        $expandExpressions = StringUtils::splitODataExpression((string) $expandQuery);

        foreach ($expandExpressions as $expand) {
            $expand = trim($expand);

            $this->processExpandExpression($expand, $this->subject);
        }
    }

    /**
     * Recursively process an expand expression and apply it to the builder.
     *
     * @param string  $expand
     * @param Builder<TModelClass> $builder
     * @param string  $parentRelation
     *
     * @return void
     * @throws ReflectionException
     */
    private function processExpandExpression(string $expand, Builder $builder, string $parentRelation = ''): void
    {
        if (Str::contains($expand, '(')) {
            [$relation, $details] = $this->parseExpandWithDetails($expand);

            if ($relation && $details) {
                // Determine if the relation is expandable
                $expandable = $this->isPropertyExpandable($relation, $parentRelation);

                if ($expandable) {
                    $this->addSelectForExpand($builder, $relation);

                    $builder->with([$expandable => function ($query) use ($details, $expandable) {
                        $this->handleExpandDetails($query, $details, $expandable);
                    }]);
                }
            }
        } else {
            // Simple relation without nested expands
            $expandable = $this->isPropertyExpandable($expand, $parentRelation);

            if ($expandable) {
                $this->addSelectForExpand($builder, $expand);

                $builder->with([$expandable => function ($query) {
                    $this->resolveToDefaultSelects($query);
                }]);
            }
        }
    }

    /**
     * Handle expand details such as $filter, $select, and nested $expand.
     *
     * @param Builder<TModelClass>|Relation<TModelClass, TRelatedModel, Collection<int, TRelatedModel>> $builder
     * @param string $details
     * @param string $relation
     *
     * @return void
     * @throws ReflectionException
     */
    private function handleExpandDetails(Builder|Relation $builder, string $details, string $relation): void
    {
        // If $builder is a Relation, get the underlying Builder
        if ($builder instanceof Relation) {
            $builder = $builder->getQuery();
        }


        // First, split the details by semicolons to separate different options
        $parsedOptions = StringUtils::getSortedDetails($details, ';');

        foreach ($parsedOptions as $option) {
            // Check if the option starts with a known key
            if (Str::startsWith(strtolower($option), '$select=')) {
                $value = substr($option, strlen('$select='));
                $this->handleSelect($builder, $value, $relation);
            } elseif (Str::startsWith(strtolower($option), '$filter=')) {
                $value = substr($option, strlen('$filter='));
                $this->handleFilter($builder, $value);
            } elseif (Str::startsWith(strtolower($option), '$expand=')) {
                $value = substr($option, strlen('$expand='));

                // Split the $expand value by commas, respecting nested parentheses
                $expandExpressions = StringUtils::splitODataExpression($value, ',');

                foreach ($expandExpressions as $expr) {
                    $expr = trim($expr);
                    if (!empty($expr)) {
                        $this->processExpandExpression($expr, $builder, $relation);
                    }
                }
            } else {
                // Assume it's an implied $expand without a prefix
                $this->processExpandExpression($option, $builder, $relation);
            }
        }

        // If no $select is specified, apply default selects
        if (!collect($parsedOptions)->contains(function($option) {
            return Str::startsWith(strtolower($option), '$select=');
        })) {
            $this->resolveToDefaultSelects($builder);
        }
    }

    /**
     * Parse an expand expression that contains details (e.g., filters, selects).
     *
     * @param string $expand
     *
     * @return array{0: string|null, 1: string|null} [relation, details] or [null, null] if parsing fails
     */
    private function parseExpandWithDetails(string $expand): array
    {
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\((.+)\)$/', $expand, $matches)) {
            return [trim($matches[1]), trim($matches[2])];
        }

        return [null, null];
    }


    /**
     * Parse a detail string into key and value.
     *
     * @param string $detail
     *
     * @return array{0: string, 1: string} [key, value]
     */
    private function parseDetail(string $detail): array
    {
        $parts = explode('=', $detail, 2);
        return [trim($parts[0]), trim($parts[1] ?? '')];
    }

    /**
     * Handle the $select part of an expand.
     *
     * @param Builder<TModelClass> $builder
     * @param string  $value
     * @param string  $expandable
     *
     * @return void
     */
    private function handleSelect(Builder $builder, string $value, string $expandable): void
    {
        if ($this->select) {
            $this->addSelectForExpand($builder, $expandable);
            $this->appendSelectQuery($value, $builder);
        }
    }

    /**
     * Handle the $filter part of an expand.
     *
     * @param Builder<TModelClass> $builder
     * @param string  $value
     *
     * @return void
     * @throws ReflectionException
     */
    private function handleFilter(Builder $builder, string $value): void
    {
        $this->appendFilterQuery($value, $builder);
    }

    /**
     * Handle the $orderby part of an expand.
     *
     * @param Builder<TModelClass> $builder
     * @param string  $value
     *
     * @return void
     */
    private function handleOrderBy(Builder $builder, string $value): void
    {
        $this->appendOrderBy($value, $builder);
    }

    /**
     * Retrieve the related model for a given expandable relation.
     *
     * @param Builder<TModelClass> $builder
     * @param string  $expandable
     *
     * @return Model
     * @throws ReflectionException
     */
    private function getRelatedModel(Builder $builder, string $expandable): Model
    {
        /** @var Model $model */
        $model = $builder->getModel();

        foreach (explode('.', $expandable) as $relation) {
            if (method_exists($model, $relation)) {
                $model = $model->$relation()->getRelated();
            } else {
                throw new \InvalidArgumentException("Relation '{$relation}' does not exist on model " . get_class($model));
            }
        }

        return $model;
    }
}
