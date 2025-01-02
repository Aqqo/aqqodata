<?php

namespace Aqqo\OData\Traits;

use Aqqo\OData\Utils\StringUtils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use ReflectionClass;
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

            if (Str::contains($expand, '(')) {
                [$relation, $details] = $this->parseExpandWithDetails($expand);
                if ($relation && $details) {
                    /** @var Builder<TModelClass> $parentBuilder */
                    $parentBuilder = $this->subject;
                    $this->handleExpandDetails($parentBuilder, $details, $relation);
                }
            } else {
                $this->applySimpleExpand($expand);
            }
        }
    }

    /**
     * Parse an expand expression that contains details (e.g., filters, selects).
     *
     * @param string $expand
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
     * Apply a simple expand without any additional details.
     *
     * @param string $expand
     * @return void
     */
    private function applySimpleExpand(string $expand): void
    {
        $expandable = $this->isPropertyExpandable($expand);

        if ($expandable) {
            $this->addSelectForExpand($this->subject, $expandable);
            $this->subject->with([$expandable => function ($builder) {
                $this->resolveToDefaultSelects($builder);
            }]);
        }
    }

    /**
     * Handle expand details such as $filter, $select, and nested $expand.
     *
     * @param Builder<TModelClass> $parentBuilder
     * @param string $details
     * @param string $relation
     * @return void
     * @throws ReflectionException
     */
    private function handleExpandDetails(Builder $parentBuilder, string $details, string $relation): void
    {
        $expandable = $this->isPropertyExpandable($relation);

        if (!$expandable) {
            return;
        }

        $model = $this->getRelatedModel($parentBuilder, $expandable);

        $this->addSelectForExpand($parentBuilder, $expandable);

        $parentBuilder->with($expandable, function (Relation $relationshipBuilder) use ($expandable, $details, $relation, $model) {
            /**
             * @var Relation<TModelClass, TRelatedModel, TModelClass> $relationshipBuilder
             */
            $parsedDetails = StringUtils::getSortedDetails($details);

            $selects_done = false;
            foreach ($parsedDetails as $detail) {
                [$key, $value] = $this->parseDetail($detail);

                switch ($key) {
                    case '$select':
                        $this->handleSelect($relationshipBuilder->getQuery(), $value, $expandable);
                        $selects_done = true;
                        break;

                    case '$filter':
                        $this->handleFilter($relationshipBuilder->getQuery(), $value);
                        break;

                    case '$expand':
                        $this->handleNestedExpand($relationshipBuilder->getQuery(), $value, $relation, $model);
                        break;

                    case '$orderby':
                        $this->handleOrderBy($relationshipBuilder->getQuery(), $value);
                        break;
                }
            }

            if (!$selects_done) {
                $this->resolveToDefaultSelects($relationshipBuilder);
            }
        });
    }

    /**
     * Parse a detail string into key and value.
     *
     * @param string $detail
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
     * @param Builder<TModelClass> $relationshipBuilder
     * @param string $value
     * @param string $expandable
     * @return void
     */
    private function handleSelect(Builder $relationshipBuilder, string $value, string $expandable): void
    {
        if ($this->select) {
            $this->addSelectForExpand($relationshipBuilder, $expandable);
            $this->appendSelectQuery($value, $relationshipBuilder);
        }
    }

    /**
     * Handle the $filter part of an expand.
     *
     * @param Builder<TModelClass> $relationshipBuilder
     * @param string $value
     * @return void
     * @throws ReflectionException
     */
    private function handleFilter(Builder $relationshipBuilder, string $value): void
    {
        $this->appendFilterQuery($value, $relationshipBuilder);
    }

    /**
     * Handle nested $expand within an expand.
     *
     * @param Builder<TModelClass> $relationshipBuilder
     * @param string $value
     * @param string $parentRelation
     * @param Model $model
     * @return void
     * @throws ReflectionException
     */
    private function handleNestedExpand(Builder $relationshipBuilder, string $value, string $parentRelation, Model $model): void
    {
        if (Str::contains($value, '(')) {
            [$nestedRelation, $nestedDetails] = $this->parseExpandWithDetails($value);
            if ($nestedRelation && $nestedDetails) {
                $fullRelation = "{$parentRelation}.{$nestedRelation}";
                $this->handleExpandDetails($relationshipBuilder, $nestedDetails, $fullRelation);
            }
        } else {
            foreach (explode(',', $value) as $relation) {
                $nestedExpandable = $this->isPropertyExpandable($relation, (new ReflectionClass($model))->getShortName());

                if ($nestedExpandable) {
                    $relationshipBuilder->with([$nestedExpandable => function ($builder) {
                        $this->resolveToDefaultSelects($builder);
                    }]);
                }
            }
        }
    }

    /**
     * @param Builder<TModelClass> $relationshipBuilder
     * @param string $value
     * @return void
     */
    private function handleOrderBy(Builder $relationshipBuilder, string $value): void
    {
        $this->appendOrderBy($value, $relationshipBuilder);
    }

    /**
     * Retrieve the related model for a given expandable relation.
     *
     * @param Builder<TModelClass> $builder
     * @param string $expandable
     * @return Model
     * @throws ReflectionException
     * @phpstan-param Builder<TModelClass> $builder
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
