<?php
namespace Aqqo\OData\Services;

use Illuminate\Database\Eloquent\Builder;
use Aqqo\OData\Query;
use Aqqo\OData\Parameters\FilterParameter;
use Aqqo\OData\Services\Expressions\ExpressionNode;
use Aqqo\OData\Services\Expressions\LogicalExpressionNode;
use Aqqo\OData\Services\Expressions\ComparisonExpressionNode;

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

    public function execute(ExpressionNode $expr, string $boolean = 'where'): void
    {
        if ($expr instanceof LogicalExpressionNode) {
            $this->applyLogical($expr, $boolean);
            return;
        }
        if ($expr instanceof ComparisonExpressionNode) {
            $this->applyComparison($expr, $boolean);
        }
    }

    protected function applyLogical(LogicalExpressionNode $node, string $boolean): void
    {
        $children = $node->children();
        $isOr = $node->isOr();

        // Handle OR nodes
        if ($isOr) {
            $this->applyOrNode($children, $boolean);
            return;
        }

        // Handle AND nodes
        $this->applyAndNode($children, $boolean);
    }

    protected function applyOrNode(array $children, string $boolean): void
    {
        if ($boolean !== 'where') {
            $this->builder->{$boolean}(function (Builder $q) use ($children) {
                $exec = new self($this->query, $q, false);
                foreach ($children as $i => $child) {
                    $exec->execute($child, $i === 0 ? 'where' : 'orWhere');
                }
            });
        } else {
            foreach ($children as $i => $child) {
                $this->execute($child, $i === 0 ? 'where' : 'orWhere');
            }
        }
    }

    protected function applyAndNode(array $children, string $boolean): void
    {
        // Check if we can flatten the AND conditions
        $canFlatten = $this->isRoot && !$this->hasOrChild($children) && !$this->hasLikeChild($children);
        
        if ($canFlatten) {
            foreach ($children as $child) {
                $this->execute($child, 'where');
            }
            return;
        }

        // Group AND conditions with proper parentheses
        $this->builder->{$boolean}(function (Builder $q) use ($children) {
            foreach ($children as $child) {
                $q->where(function (Builder $inner) use ($child) {
                    $exec = new self($this->query, $inner, false);
                    $exec->execute($child, 'where');
                });
            }
        });
    }

    protected function applyComparison(ComparisonExpressionNode $node, string $boolean): void
    {
        $param = $node->parameter();
        $relation = $param->getRelation();
        $column = $param->getColumn();

        if (!empty($relation)) {
            $this->applyRelationFilter($param, $relation, $column);
            return;
        }

        $this->applyDirectFilter($param, $column, $boolean);
    }

    protected function applyRelationFilter(FilterParameter $param, array $relation, string $column): void
    {
        $relName = array_shift($relation);
        if ($this->query->isPropertyExpandable($relName) === false) {
            return;
        }

        $method = $param->getLambda() === 'all' ? 'whereDoesntHave' : 'whereHas';
        $this->builder->{$method}($relName, function (Builder $q) use ($column, $param) {
            $op = $param->getOperator();
            $value = $param->getValue();
            $table = $q->getModel()->getTable();
            $key = $table . '.' . $column;

            if (strtolower($op) === 'in') {
                $q->whereIn($key, (array) $value);
            } else {
                $q->where($key, $op, $value);
            }
        });
    }

    protected function applyDirectFilter(FilterParameter $param, string $column, string $boolean): void
    {
        $mapped = $this->query->isPropertyFilterable($column);
        if ($mapped === false) {
            return;
        }

        $table = $this->builder->getModel()->getTable();
        $qualified = $table . '.' . $mapped;
        $op = $param->getOperator();
        $value = $param->getValue();

        if (strtolower($op) === 'in') {
            $suffix = $param->isInverse() ? 'NotIn' : 'In';
            $method = ($boolean === 'where' ? 'where' : 'orWhere') . $suffix;
            $this->builder->{$method}($qualified, (array)$value);
            return;
        }

        $this->builder->{$boolean}($qualified, $op, $value);
    }

    protected function hasOrChild(array $children): bool
    {
        foreach ($children as $child) {
            if ($child instanceof LogicalExpressionNode && $child->isOr()) {
                return true;
            }
        }
        return false;
    }

    protected function hasLikeChild(array $children): bool
    {
        foreach ($children as $child) {
            if ($child instanceof ComparisonExpressionNode && 
                strtolower($child->parameter()->getOperator()) === 'like') {
                return true;
            }
        }
        return false;
    }
}
