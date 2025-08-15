<?php

namespace Aqqo\OData\Filters;

use Illuminate\Database\Eloquent\Builder;
use Aqqo\OData\Utils\OperatorUtils;

/**
 * Filter for function-based operations (contains, startswith, endswith)
 */
class FunctionFilter extends AbstractFilter
{
    private string $function;

    public function __construct(string $function, string $column, mixed $value)
    {
        $this->function = strtolower($function);
        $this->column = $column;
        $this->value = $value;
        $this->operator = OperatorUtils::mapOperator($function);
    }

    public function apply(Builder $builder): void
    {
        $model = $builder->getModel();
        
        if (!$this->isValidForModel($model)) {
            return;
        }

        $column = $this->getQualifiedColumn($model);
        
        // Handle table name qualification for joins
        if (!str_contains($column, '.')) {
            $query = $builder->getQuery();
            $joins = $query->joins ?? [];
            
            if (!empty($joins)) {
                $column = "{$model->getTable()}.{$this->column}";
            }
        }

        $value = OperatorUtils::getValueBasedOnOperator($this->function, $this->value);
        $builder->where($column, $this->operator, $value);
    }

    public function toString(): string
    {
        return "{$this->function}({$this->column}, '{$this->value}')";
    }
}
