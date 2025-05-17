<?php
namespace Aqqo\OData\Services;

use Illuminate\Database\Eloquent\Builder;
use Aqqo\OData\Query;
use Aqqo\OData\Services\Expressions\ExpressionNode;
use Aqqo\OData\Services\Expressions\LogicalExpressionNode;
use Aqqo\OData\Services\Expressions\ComparisonExpressionNode;

class FilterExecutor
{
    protected Query   $query;
    protected Builder $builder;

    public function __construct(Query $query, Builder $builder)
    {
        $this->query   = $query;
        $this->builder = $builder;
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
        $isRoot   = $boolean === 'where';
        $children = $node->children();

        // 1) OR nodes: simple OR at root, nested OR groups are grouped
        if ($node->isOr()) {
            if ($boolean !== 'where') {
                $this->builder->{$boolean}(function (Builder $q) use ($children) {
                    $exec = new self($this->query, $q);
                    foreach ($children as $i => $child) {
                        $exec->execute($child, $i === 0 ? 'where' : 'orWhere');
                    }
                });
            } else {
                foreach ($children as $i => $child) {
                    $this->execute($child, $i === 0 ? 'where' : 'orWhere');
                }
            }
            return;
        }

        // 2) AND nodes: flatten simple AND, group all others
        $hasOrChild = false;
        foreach ($children as $c) {
            if ($c instanceof LogicalExpressionNode && $c->isOr()) {
                $hasOrChild = true;
                break;
            }
        }
        if ($isRoot && ! $hasOrChild && ! $this->hasLikeChild($children)) {
            foreach ($children as $child) {
                $this->execute($child, 'where');
            }
            return;
        }

        // 3) any other AND must be grouped; each child wrapped in its own nested where for parentheses
        $this->builder->{$boolean}(function (Builder $q) use ($children) {
            foreach ($children as $child) {
                $q->where(function (Builder $inner) use ($child) {
                    $exec = new self($this->query, $inner);
                    $exec->execute($child, 'where');
                });
            }
        });
    }

    protected function applyComparison(ComparisonExpressionNode $node, string $boolean): void
    {
        $param    = $node->parameter();
        $relation = $param->getRelation();  // array<string> of path segments
        $column   = $param->getColumn();    // OData property name

        // 1) ANY/ALL relationship filters
        if (! empty($relation)) {
            $relName = array_shift($relation);
            if ($this->query->isPropertyExpandable($relName) === false) {
                // skip filters for non-expandable relations
                return;
            }
            $method  = $param->getLambda() === 'all' ? 'whereDoesntHave' : 'whereHas';

            $this->builder->{$method}($relName, function (Builder $q) use ($column, $param) {
                $op    = $param->getOperator();        // already SQL (=, <>, IN, etc)
                $value = $param->getValue();
                $table = $q->getModel()->getTable();
                $key   = $table . '.' . $column;
                if (strtolower($op) === 'in') {
                    $q->whereIn($key, (array) $value);
                } else {
                    $q->where($key, $op, $value);
                }
            });
            return;
        }

        // 2) direct‑property filter: ask the Query if this property is filterable
        $mapped = $this->query->isPropertyFilterable($column);
        if ($mapped === false) {
            // unknown or disallowed property → silently skip
            return;
        }

        // Qualify the column with the source table name
        $table     = $this->builder->getModel()->getTable();
        $qualified = $table . '.' . $mapped;

        $op    = $param->getOperator();
        $value = $param->getValue();

        // special‑case IN / NOT IN
        if (strtolower($op) === 'in') {
            $suffix = $param->isInverse() ? 'NotIn' : 'In';
            $method = ($boolean === 'where' ? 'where' : 'orWhere') . $suffix;
            $this->builder->{$method}($qualified, (array)$value);
            return;
        }

        // all other operators
        $this->builder->{$boolean}($qualified, $op, $value);
    }

    /**
     * Determine if any of the comparison children use a LIKE operator,
     * indicating function-based filters (contains, startswith, endswith).
     *
     * @param  ExpressionNode[]  $children
     * @return bool
     */
    private function hasLikeChild(array $children): bool
    {
        foreach ($children as $child) {
            if ($child instanceof ComparisonExpressionNode && strtolower($child->parameter()->getOperator()) === 'like') {
                return true;
            }
        }
        return false;
    }
}
