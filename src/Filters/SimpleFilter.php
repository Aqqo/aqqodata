<?php

namespace Aqqo\OData\Filters;

use Illuminate\Database\Eloquent\Builder;
use Aqqo\OData\Utils\OperatorUtils;

/**
 * Simple filter for basic field comparisons
 */
class SimpleFilter extends AbstractFilter
{
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

        if (strtolower($this->operator) === 'in') {
            if (is_array($this->value)) {
                $builder->whereIn($column, $this->value);
            } else {
                // Extract values from string format ('value1', 'value2')
                preg_match("/\((.*?)\)/", (string)$this->value, $matches);
                if (isset($matches[1])) {
                    $values = array_map(function($val) {
                        return trim($val, "'");
                    }, explode(',', $matches[1]));
                    $builder->whereIn($column, $values);
                }
            }
        } else {
            $builder->where($column, $this->operator, $this->value);
        }
    }

    public function toString(): string
    {
        $value = is_array($this->value) 
            ? '(' . implode(',', array_map(fn($v) => "'{$v}'", $this->value)) . ')'
            : "'{$this->value}'";
        
        return "{$this->column} {$this->operator} {$value}";
    }
}
