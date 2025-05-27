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
            // Always delegate to applyLogical; it will decide on grouping
            $this->applyLogical($expr, $boolean);
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
        // Avoid wrapping ANDs unnecessarily
        $isLeftOr = $left instanceof CompositeQueryNode && strtolower($left->getOperator()) === 'or';
        $isRightOr = $right instanceof CompositeQueryNode && strtolower($right->getOperator()) === 'or';

        if ($isLeftOr || $isRightOr) {
            // Needs grouping to preserve precedence
            $this->builder->{$boolean}(function (Builder $q) use ($left, $right) {
                $exec = new self($this->query, $q, false);
                $exec->execute($left, 'where');
                $exec->execute($right, 'where');
            });
        } else {
            // Flat AND: no grouping needed
            $this->execute($left, $boolean);
            $this->execute($right, $boolean);
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
            $this->builder->{$method}($qualified, $this->formatInValues($value));
            return;
        }

        // If value is null, use the appropriate null check method
        if ($value === "null") {
            $method = ($boolean === 'where' ? 'where' : 'orWhere') . 'Null';
            $this->builder->{$method}($qualified);
            return;
        }

        if ($value === "!null") {
            $method = ($boolean === 'where' ? 'where' : 'orWhere') . 'NotNull';
            $this->builder->{$method}($qualified);
            return;
        }

        $this->builder->{$boolean}($qualified, $operator, $this->formatValue($value));
    }

    protected function formatInValues($value): array
    {
        if (is_string($value) && str_contains($value, "(") && str_contains($value, ")")) {
            $value = trim($value, "()");
            return array_map(function($v) {
                $v = trim($v);
                $v = trim($v, "'");
                return is_numeric($v) ? (int)$v : $v;
            }, explode(',', $value));
        }
        
        return array_map(function($v) {
            $v = trim($v, "'");
            return is_numeric($v) ? (int)$v : $v;
        }, (array)$value);
    }

    protected function formatValue($value): string
    {
        if (!is_string($value)) {
            return (string)$value;
        }

        $value = trim($value, "'");

        return $value;
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

    // TODO: Add support for LIKE Operators
    // TODO: Add support for Exists operators
    // TODO: Add support for Not Exists operators
}
