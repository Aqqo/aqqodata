<?php

namespace Aqqo\OData\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * Abstract base class for all filters
 */
abstract class AbstractFilter implements FilterInterface
{
    protected string $column;
    protected string $operator;
    protected mixed $value;
    protected ?string $table = null;

    public function __construct(string $column, string $operator, mixed $value)
    {
        $this->column = $column;
        $this->operator = $operator;
        $this->value = $value;
    }

    /**
     * Validate if the filter can be applied to the given model
     *
     * @param Model $model
     * @return bool
     */
    protected function isValidForModel(Model $model): bool
    {
        $modelClass = get_class($model);
        $reflection = new ReflectionClass($modelClass);
        $shortName = $reflection->getShortName();

        // Check if the property is filterable (this would need to be implemented in the trait)
        // For now, we'll assume it's valid if the column exists in the model's table
        return $this->isPropertyFilterable($this->column, $shortName);
    }

    /**
     * Get the qualified column name for the given model
     *
     * @param Model $model
     * @return string
     */
    protected function getQualifiedColumn(Model $model): string
    {
        if (str_contains($this->column, '.')) {
            return $this->column;
        }

        $table = $this->table ?? $model->getTable();
        return "{$table}.{$this->column}";
    }

    /**
     * Check if a property is filterable (to be implemented by the trait)
     *
     * @param string $property
     * @param string $modelName
     * @return bool|string
     */
    protected function isPropertyFilterable(string $property, string $modelName): bool|string
    {
        // This will be injected by the trait
        if (is_callable($this->validationCallback)) {
            return call_user_func($this->validationCallback, $property, $modelName);
        }
        return true;
    }

    /**
     * @var callable|null
     */
    protected $validationCallback = null;

    /**
     * Get the string representation of the filter
     *
     * @return string
     */
    public function toString(): string
    {
        $value = is_array($this->value) 
            ? '(' . implode(',', array_map(fn($v) => "'{$v}'", $this->value)) . ')'
            : "'{$this->value}'";
        
        return "{$this->column} {$this->operator} {$value}";
    }
}
