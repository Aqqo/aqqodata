<?php

namespace Aqqo\OData\Traits;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait AttributesTrait
{
    /** @var array<string, array<string, string>> */
    private $selectables = [];

    /** @var array<string, array<string, string>> */
    private $defaultSelectables = [];

    /** @var array<string, array<string, string>> */
    private $filterables = [];

    /** @var array<string, array<string, string>> */
    private $searchables = [];

    /** @var array<string, array<string, string>> */
    private $orderables = [];

    /** @var array<string, array<string, string>> */
    private $expandables = [];

    /**
     * @return void
     * @throws \ReflectionException
     */
    protected function handleAttributes(): void
    {
        $this->handleModel($this->subject);
    }

    /**
     * @param Builder<Model> $builder
     * @return void
     * @throws \ReflectionException
     */
    private function handleModel(Builder $builder): void
    {
        $model = $builder->getModel();
        $reflectionClass = new \ReflectionClass($model);
        $shortName = strtolower($reflectionClass->getShortName());

        foreach ($this->getODataPropertyAttributes($reflectionClass) as $attribute) {
            /** @var ODataProperty $instance */
            $instance = $attribute->newInstance();

            $db_column = $instance->getSource() ?? $instance->getName();
            $odata_column = $instance->getName();

            // Support for dynamic resolver.
            $resolver_method = 'oData'.ucfirst($instance->getName()).'Resolver';
            if (empty($instance->getSource()) && method_exists($model, $resolver_method)) {
                $db_column = $model->{$resolver_method}();
            }

            if ($instance->isSelectable()) {
                $this->selectables[$shortName][$odata_column] = $db_column;
                
                if ($instance->isDefaultSelectable()) {
                    $this->defaultSelectables[$shortName][$odata_column] = $db_column;
                }
            }

            if ($instance->isFilterable()) {
                $this->filterables[$shortName][$odata_column] = $db_column;
            }

            if ($instance->isSearchable()) {
                $this->searchables[$shortName][$odata_column] = $db_column;
            }

            if ($instance->isOrderable()) {
                $this->orderables[$shortName][$odata_column] = $db_column;
            }
        }

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $reflectionAttributes = $reflectionMethod->getAttributes(ODataRelationship::class, \ReflectionAttribute::IS_INSTANCEOF);
            $relationshipInstance = $reflectionAttributes ? Arr::first($reflectionAttributes)?->newInstance() : null;
            if ($relationshipInstance) {
                /** @var ODataRelationship $relationshipInstance */
                $this->expandables[$shortName][strtolower($relationshipInstance->getName())] = $relationshipInstance->getSource() ?? $reflectionMethod->getName();

                $model = $builder->getModel()->{$reflectionMethod->getName()}()->getModel();
                $reflection = new \ReflectionClass($model);

                if (!array_key_exists(strtolower($reflection->getShortName()), $this->expandables)) {
                    $this->handleModel($model->newQuery());
                }
            }
        }
    }

    /**
     * Get ODataProperty attributes for a class, including inherited attributes from parent classes.
     *
     * Child class attributes are returned last so they can override parent mappings.
     *
     * @param \ReflectionClass<object> $reflectionClass
     * @return array<int, \ReflectionAttribute>
     */
    protected function getODataPropertyAttributes(\ReflectionClass $reflectionClass): array
    {
        $class_hierarchy = [];
        $current = $reflectionClass;
        while ($current !== false) {
            $class_hierarchy[] = $current;
            $current = $current->getParentClass();
        }

        $attributes = [];
        foreach (array_reverse($class_hierarchy) as $class_reflection) {
            foreach ($class_reflection->getAttributes(ODataProperty::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * Build OData-shaped attributes for a loaded model using default-selectable #[ODataProperty] metadata.
     *
     * Used when serializing expanded relations (e.g. MorphTo) where the concrete type is only known at runtime.
     *
     * @return array<string, mixed>
     */
    protected function getRuntimeDefaultSelectedAttributesForModel(Model $item): array
    {
        $attributes = [];
        $reflectionClass = new \ReflectionClass($item);

        foreach ($this->getODataPropertyAttributes($reflectionClass) as $attribute) {
            /** @var ODataProperty $instance */
            $instance = $attribute->newInstance();

            if (! $instance->isSelectable() || ! $instance->isDefaultSelectable()) {
                continue;
            }

            $odata_column = $instance->getName();
            $db_column = $instance->getSource() ?? $instance->getName();

            $resolver_method = 'oData'.ucfirst($instance->getName()).'Resolver';
            if (empty($instance->getSource()) && method_exists($item, $resolver_method)) {
                $db_column = $item->{$resolver_method}();
            }

            $attributes[$odata_column] = $item->getAttribute($db_column);
        }

        return $attributes;
    }

    /**
     * @param string $property
     * @param string|null $className
     * @return string|bool
     */
    protected function isPropertySelectable(string $property, string|null $className = null): string|bool
    {
        return $this->isProperty($this->selectables, $property, $className);
    }

    /**
     * @param string $property
     * @param string|null $className
     * @return string|bool
     */
    protected function isPropertyFilterable(string $property, string|null $className = null): string|bool
    {
        return $this->isProperty($this->filterables, $property, $className);
    }

    /**
     * @param string $property
     * @param string|null $className
     * @return bool
     */
    protected function isPropertySearchable(string $property, string|null $className = null): string|bool
    {
        return $this->isProperty($this->searchables, $property, $className);
    }

    /**
     * @param string $property
     * @param string|null $className
     * @return bool
     */
    protected function isPropertyOrderable(string $property, string|null $className = null): string|bool
    {
        return $this->isProperty($this->orderables, $property, $className);
    }

    /**
     * @param array<string, array<string, string>> $array
     * @param string $property
     * @param string|null $className
     * @return string|bool
     */
    private function isProperty(array $array, string $property, string|null $className = null): string|bool
    {
        $className ??= $this->subjectModelReflectionClass->getShortName();
        if (empty($array)) {
            return true;
        } else if (str_contains($property, '.')) {
            [$className, $property] = array_slice(explode('.', $property), -2, 2);
        }
        $className = strtolower($className);
        return $array[$className][$property] ?? false;
    }

    /**
     * @param string $property
     * @param string|null $className
     * @return false|string
     */
    protected function isPropertyExpandable(string $property, string|null $className = null): false|string
    {
        // If className is not provided, get it from the subject model
        if (empty($className)) {
            $className = $this->subjectModelReflectionClass->getShortName();
        }
        $classNameLower = strtolower(Str::singular($className));

        // Handle nested properties
        if (Str::contains($property, '.')) {
            $segments = explode('.', $property);
            $currentClass = $classNameLower;

            foreach ($segments as $segment) {
                $segmentLower = strtolower($segment);

                if (isset($this->expandables[$currentClass][$segmentLower])) {
                    $currentClass = strtolower(Str::singular($this->expandables[$currentClass][$segmentLower]));
                } else {
                    return false;
                }
            }

            return $currentClass;
        }

        // If expandables are not defined, assume the property is expandable
        if (empty($this->expandables)) {
            return $property;
        }

        // Singularize and lowercase the class name for lookup
        $propertyLower = strtolower($property);

        if (isset($this->expandables[$classNameLower][$propertyLower])) {
            return $this->expandables[$classNameLower][$propertyLower];
        }

        return false;
    }

    /**
     * @param string|null $className
     * @return array<int, string>|string[]
     */
    protected function getSearchables(string|null $className = null): array
    {
        $className ??= $this->subjectModelReflectionClass->getShortName();
        return $this->searchables[strtolower($className)] ?? [];
    }
}
