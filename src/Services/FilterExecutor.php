<?php
namespace Aqqo\OData\Services;

use Illuminate\Database\Eloquent\Builder;
use Aqqo\OData\Query;
use Aqqo\OData\Parameters\FilterParameter;
use Aqqo\OData\QueryNodeStructure\QueryNode;
use Aqqo\OData\QueryNodeStructure\BasicQueryNode;
use Aqqo\OData\QueryNodeStructure\CompositeQueryNode;

use function PHPUnit\Framework\stringContains;

class FilterExecutor
{
    protected Query $query;
    protected Builder $builder;
    protected bool $isRoot = true;

    public function __construct(Query $query, Builder $builder, bool $isRoot = true)
    {
        $this->query = $query;
        $this->builder = $builder;
        $this->isRoot = $isRoot;
    }

    public function execute(QueryNode $expr, string $boolean = 'where'): void
    {
        if ($expr instanceof CompositeQueryNode) {
            // For composite nodes, we need to check if we're at root level
            if ($this->isRoot) {
                // At root level, execute directly without wrapping
                $this->applyLogical($expr, $boolean);
            } else {
                // For nested conditions, wrap in parentheses
                $this->builder->{$boolean}(function (Builder $q) use ($expr) {
                    $exec = new self($this->query, $q, false);
                    $exec->applyLogical($expr, 'where');
                });
            }
            return;
        }
        if ($expr instanceof BasicQueryNode) {
            $this->applyComparison($expr, $boolean);
        }
    }

    protected function applyLogical(CompositeQueryNode $node, string $boolean): void
    {
        $operator = strtolower($node->getOperator());
        $left = $node->getLeft();
        $right = $node->getRight();

        if ($operator === 'or') {
            $this->applyOrNode($left, $right, $boolean);
        } else {
            $this->applyAndNode($left, $right, $boolean);
        }
    }

    protected function applyOrNode(QueryNode $left, QueryNode $right, string $boolean): void
    {
        if ($boolean !== 'where') {
            $this->builder->{$boolean}(function (Builder $q) use ($left, $right) {
                $exec = new self($this->query, $q, false);
                $exec->execute($left, 'where');
                $exec->execute($right, 'orWhere');
            });
        } else {
            // Always wrap OR conditions in parentheses to maintain proper precedence
            $this->builder->where(function (Builder $q) use ($left, $right) {
                $exec = new self($this->query, $q, false);
                $exec->execute($left, 'where');
                $exec->execute($right, 'orWhere');
            });
        }
    }

    protected function applyAndNode(QueryNode $left, QueryNode $right, string $boolean): void
    {
        // Check if we can flatten the AND conditions
        $canFlatten = $this->isRoot && !$this->hasOrChild([$left, $right]) && !$this->hasLikeChild([$left, $right]);
        
        if ($canFlatten) {
            $this->execute($left, 'where');
            $this->execute($right, 'where');
            return;
        }

        // Group AND conditions with proper parentheses
        if (!$this->isRoot) {
            $this->builder->{$boolean}(function (Builder $q) use ($left, $right) {
                $exec = new self($this->query, $q, false);
                $exec->execute($left, 'where');
                $exec->execute($right, 'where');
            });
        } else {
            // At root level, execute directly on the main builder
            $this->execute($left, 'where');
            $this->execute($right, 'where');
        }
    }

    protected function applyComparison(BasicQueryNode $node, string $boolean): void
    {
        $field = $node->getField();
        $operator = $this->mapOperator($node->getOperator());
        $value = $node->getValue();

        // Check if this is a relationship field
        if (str_contains($field, '/')) {
            $parts = explode('/', $field);
            $relation = array_shift($parts);
            $column = implode('/', $parts);
            $this->applyRelationFilter($relation, $column, $operator, $value);
            return;
        }

        $this->applyDirectFilter($field, $operator, $value, $boolean);
    }

    protected function applyDirectFilter(string $field, string $operator, $value, string $boolean): void
    {
        $mapped = $this->query->isPropertyFilterable($field);
        if ($mapped === false) {
            return;
        }

        $table = $this->builder->getModel()->getTable();
        $qualified = $table . '.' . $mapped;

        if (strtolower($operator) === 'in') {
            $method = ($boolean === 'where' ? 'where' : 'orWhere') . 'In';
            // Format each value in the array
            $values = array_map(function($v) {
                return $this->formatValue($v);
            }, (array)$value);
            $this->builder->{$method}($qualified, $values);
            return;
        }

        // If value is null, use the appropriate null check method
        if ($value === "null") {
            $method = ($boolean === 'where' ? 'where' : 'orWhere') . 'Null';
            $this->builder->{$method}($qualified);
            return;
        }

        $this->builder->{$boolean}($qualified, $operator, $this->formatValue($value));
    }

    protected function formatValue($value): string
    {
        
        if (is_string($value) && str_contains($value, "'")) {
            // Remove any existing quotes and trim whitespace
            $value = trim($value, "'");
            // Add single quotes
            return "'" . $value . "'";
        }
        return (string)$value;
    }

    protected function mapOperator(string $operator): string
    {
        return match (strtolower($operator)) {
            'eq' => '=',
            'ne' => '!=',
            'gt' => '>',
            'ge' => '>=',
            'lt' => '<',
            'le' => '<=',
            'contains' => 'like',
            'startswith' => 'like',
            'endswith' => 'like',
            default => $operator
        };
    }

    protected function applyRelationFilter(string $relation, string $column, string $operator, $value): void
    {
        if ($this->query->isPropertyExpandable($relation) === false) {
            return;
        }

        $this->builder->whereHas($relation, function (Builder $q) use ($column, $operator, $value) {
            $table = $q->getModel()->getTable();
            $key = $table . '.' . $column;

            if (strtolower($operator) === 'in') {
                $q->whereIn($key, (array) $value);
            } else {
                $q->where($key, $operator, $value);
            }
        });
    }

    protected function hasOrChild(array $children): bool
    {
        foreach ($children as $child) {
            if ($child instanceof CompositeQueryNode && strtolower($child->getOperator()) === 'or') {
                return true;
            }
        }
        return false;
    }

    protected function hasLikeChild(array $children): bool
    {
        foreach ($children as $child) {
            if ($child instanceof BasicQueryNode && 
                in_array(strtolower($child->getOperator()), ['contains', 'startswith', 'endswith'])) {
                return true;
            }
        }
        return false;
    }
}
