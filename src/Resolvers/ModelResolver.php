<?php

namespace Aqqo\OData\Resolvers;

use Aqqo\OData\Utils\ClassUtils;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModelResolver
{
    /**
     * @param array<string, array<string, string>> $selects
     */
    public function __construct(private array $selects = [])
    {
    }

    /**
     * @param array<string, array<string, string>> $selects
     * @return void
     */
    public function refreshSelects(array $selects): void
    {
        $this->selects = $selects;
    }

    /**
     * @param Collection<int, Model> $collection
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function resolveCollection(Collection $collection): \Illuminate\Support\Collection
    {
        return $collection->map(fn (Model $item) => $this->resolveModel($item));
    }

    /**
     * @param Model $item
     * @param bool $ignoreSelects
     * @return array<string, mixed>
     */
    public function resolveModel(Model $item, bool $ignoreSelects = false): array
    {
        $clonedItem = clone $item;

        $attributes = $ignoreSelects ? $item->getAttributes() : $this->resolveAttributes($item);

        $clonedItem->setRawAttributes($attributes);
        foreach ($clonedItem->getRelations() as $key => $relation) {
            if ($relation instanceof Collection) {
                $attributes[$key] = $this->resolveCollection($relation);
                continue;
            }

            if ($relation instanceof Model) {
                $reflectionClass = new \ReflectionClass($item);

                if ($reflectionClass->hasMethod($key)) {
                    $method = $reflectionClass->getMethod($key);
                    $returnType = $method->getReturnType();

                    if ($returnType && $returnType->getName() === MorphTo::class) {
                        $attributes[$key] = $this->resolveModel($relation, true);
                    } else {
                        $attributes[$key] = $this->resolveModel($relation);
                    }
                } else {
                    $attributes[$key] = $this->resolveModel($relation, true);
                }
            }
        }

        return $attributes;
    }

    /**
     * @param Model $item
     * @return array<string, mixed>
     */
    private function resolveAttributes(Model $item): array
    {
        $attributes = [];
        foreach ($this->selects[ClassUtils::getShortName($item)] ?? [] as $odataColumn => $dbColumn) {
            $attributes[$odataColumn] = $item->getAttribute($dbColumn);
        }

        return $attributes;
    }
}
