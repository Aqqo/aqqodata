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
        $className = $reflectionClass->getName(); // Use full class name instead of short name

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
                $this->selectables[$className][$odata_column] = $db_column;
                
                if ($instance->isDefaultSelectable()) {
                    $this->defaultSelectables[$className][$odata_column] = $db_column;
                }
            }

            if ($instance->isFilterable()) {
                $this->filterables[$className][$odata_column] = $db_column;
            }

            if ($instance->isSearchable()) {
                $this->searchables[$className][$odata_column] = $db_column;
            }

            if ($instance->isOrderable()) {
                $this->orderables[$className][$odata_column] = $db_column;
            }
        }

        foreach ($this->getODataRelationshipMethods($reflectionClass) as $reflectionMethod) {
            $reflectionAttributes = $reflectionMethod->getAttributes(ODataRelationship::class, \ReflectionAttribute::IS_INSTANCEOF);
            $relationshipInstance = $reflectionAttributes ? Arr::first($reflectionAttributes)?->newInstance() : null;
            if ($relationshipInstance) {
                /** @var ODataRelationship $relationshipInstance */
                $this->expandables[$className][strtolower($relationshipInstance->getName())] = $relationshipInstance->getSource() ?? $reflectionMethod->getName();

                // Use getRelated() instead of getModel() to get the model instance
                // This works for all relationship types (HasMany, HasOne, BelongsTo, BelongsToMany, etc.)
                $relatedModel = $builder->getModel()->{$reflectionMethod->getName()}()->getRelated();
                $relatedReflection = new \ReflectionClass($relatedModel);
                $relatedClassName = $relatedReflection->getName();

                if (!array_key_exists($relatedClassName, $this->expandables)) {
                    $this->handleModel($relatedModel->newQuery());
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
    private function getODataPropertyAttributes(\ReflectionClass $reflectionClass): array
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
     * Get methods with ODataRelationship attributes for a class, including inherited methods from parent classes.
     *
     * Child class methods are returned last so they can override parent mappings.
     *
     * @param \ReflectionClass<object> $reflectionClass
     * @return array<int, \ReflectionMethod>
     */
    private function getODataRelationshipMethods(\ReflectionClass $reflectionClass): array
    {
        $class_hierarchy = [];
        $current = $reflectionClass;
        while ($current !== false) {
            $class_hierarchy[] = $current;
            $current = $current->getParentClass();
        }

        $methods = [];
        $methodNames = [];
        foreach (array_reverse($class_hierarchy) as $class_reflection) {
            foreach ($class_reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
                // Only process methods declared in this specific class (not inherited)
                if ($reflectionMethod->getDeclaringClass()->getName() !== $class_reflection->getName()) {
                    continue;
                }

                $methodName = $reflectionMethod->getName();
                $reflectionAttributes = $reflectionMethod->getAttributes(ODataRelationship::class, \ReflectionAttribute::IS_INSTANCEOF);
                
                if ($reflectionAttributes && !empty($reflectionAttributes)) {
                    // If method already exists (from parent), replace it with child version
                    if (isset($methodNames[$methodName])) {
                        // Remove the old method
                        $methods = array_filter($methods, function($method) use ($methodName) {
                            return $method->getName() !== $methodName;
                        });
                    }
                    $methods[] = $reflectionMethod;
                    $methodNames[$methodName] = true;
                }
            }
        }

        return array_values($methods);
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
        if (empty($array)) {
            return true;
        }

        // If className is provided, try to normalize it
        if ($className !== null) {
            $normalizedKey = \Aqqo\OData\Utils\ClassUtils::normalizeClassNameForLookup($array, $className, $this->subjectModelReflectionClass);
            if ($normalizedKey !== null && isset($array[$normalizedKey][$property])) {
                return $array[$normalizedKey][$property];
            }
        }

        // Handle dot notation (nested properties)
        if (str_contains($property, '.')) {
            [$className, $property] = array_slice(explode('.', $property), -2, 2);
            $normalizedKey = \Aqqo\OData\Utils\ClassUtils::normalizeClassNameForLookup($array, $className, $this->subjectModelReflectionClass);
            if ($normalizedKey !== null && isset($array[$normalizedKey][$property])) {
                return $array[$normalizedKey][$property];
            }
        }

        // Try with subject model's full class name
        $subjectClassName = $this->subjectModelReflectionClass->getName();
        if (isset($array[$subjectClassName][$property])) {
            return $array[$subjectClassName][$property];
        }

        // Fallback: try with short name for backward compatibility
        $shortName = strtolower($this->subjectModelReflectionClass->getShortName());
        return $array[$shortName][$property] ?? false;
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
            $className = $this->subjectModelReflectionClass->getName(); // Use full class name
        } else {
            // className might be a relationship method name (from parentRelation parameter)
            // Try to resolve it to a class name first
            $subjectClassName = $this->subjectModelReflectionClass->getName();
            $normalizedSubject = \Aqqo\OData\Utils\ClassUtils::normalizeClassNameForLookup(
                $this->expandables,
                $subjectClassName,
                $this->subjectModelReflectionClass
            ) ?? $subjectClassName;
            
            // Check if className is actually a relationship name
            $classNameLower = strtolower($className);
            if (isset($this->expandables[$normalizedSubject][$classNameLower])) {
                // It's a relationship name, resolve to the related model class
                try {
                    $modelReflection = new \ReflectionClass($normalizedSubject);
                    $relationshipMethod = $this->expandables[$normalizedSubject][$classNameLower];
                    if ($modelReflection->hasMethod($relationshipMethod)) {
                        $tempModel = $modelReflection->newInstanceWithoutConstructor();
                        $relationship = $tempModel->{$relationshipMethod}();
                        $relatedModel = $relationship->getRelated();
                        $className = get_class($relatedModel);
                    }
                } catch (\Exception $e) {
                    // If we can't resolve, treat it as a class name
                }
            }
        }

        // Normalize the class name for lookup
        $normalizedClassName = \Aqqo\OData\Utils\ClassUtils::normalizeClassNameForLookup(
            $this->expandables,
            $className,
            $this->subjectModelReflectionClass
        ) ?? $className;

        // Handle nested properties (dot notation)
        if (Str::contains($property, '.')) {
            $segments = explode('.', $property);
            $currentClass = $normalizedClassName;

            foreach ($segments as $segment) {
                $segmentLower = strtolower($segment);

                if (isset($this->expandables[$currentClass][$segmentLower])) {
                    $relationshipMethod = $this->expandables[$currentClass][$segmentLower];
                    
                    // Resolve the related model class from the relationship
                    try {
                        $modelReflection = new \ReflectionClass($currentClass);
                        if ($modelReflection->hasMethod($relationshipMethod)) {
                            // Get the related model class from the relationship
                            // We need to instantiate the model to call the relationship method
                            $tempModel = $modelReflection->newInstanceWithoutConstructor();
                            $relationship = $tempModel->{$relationshipMethod}();
                            $relatedModel = $relationship->getRelated();
                            $relatedClassName = get_class($relatedModel);
                            
                            // Use the related class name for the next iteration
                            // Check if this related class is in our expandables (it should be, since we process it in handleModel)
                            if (isset($this->expandables[$relatedClassName])) {
                                $currentClass = $relatedClassName;
                            } else {
                                // If not in expandables yet, we can still use it (it will be processed if needed)
                                $currentClass = $relatedClassName;
                            }
                        } else {
                            return false;
                        }
                    } catch (\Exception $e) {
                        // If we can't resolve the relationship, return false
                        return false;
                    }
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

        // Look up the property
        $propertyLower = strtolower($property);

        if (isset($this->expandables[$normalizedClassName][$propertyLower])) {
            return $this->expandables[$normalizedClassName][$propertyLower];
        }

        return false;
    }

    /**
     * @param string|null $className
     * @return array<int, string>|string[]
     */
    protected function getSearchables(string|null $className = null): array
    {
        $className ??= $this->subjectModelReflectionClass->getName();
        $normalizedKey = \Aqqo\OData\Utils\ClassUtils::normalizeClassNameForLookup(
            $this->searchables,
            $className,
            $this->subjectModelReflectionClass
        ) ?? $this->subjectModelReflectionClass->getName();
        return $this->searchables[$normalizedKey] ?? [];
    }
}
