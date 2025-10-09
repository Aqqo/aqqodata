<?php
namespace Aqqo\OData\Services;

use Aqqo\OData\Query;
use Aqqo\OData\QueryNodeStructure\BasicQueryNode;
use Aqqo\OData\QueryNodeStructure\CompositeQueryNode;
use Aqqo\OData\QueryNodeStructure\LambdaQueryNode;
use Aqqo\OData\QueryNodeStructure\NotQueryNode;
use Aqqo\OData\QueryNodeStructure\QueryNode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

class FilterExecutor
{
    protected Query $query;
    protected Builder $builder;
    protected bool $isRoot;
    /** @var array<string, true> */
    protected array $lambdaAliases;

    public function __construct(Query $query, Builder $builder, bool $isRoot = true, array $lambdaAliases = [])
    {
        $this->query = $query;
        $this->builder = $builder;
        $this->isRoot = $isRoot;
        $this->lambdaAliases = $lambdaAliases;
    }

    public function execute(QueryNode $expr, string $boolean = 'where', bool $wrap = false): void
    {
        if ($expr instanceof CompositeQueryNode) {
            $this->applyLogical($expr, $boolean, $wrap);
            return;
        }

        if ($expr instanceof LambdaQueryNode) {
            $this->applyLambda($expr, $boolean);
            return;
        }

        if ($expr instanceof NotQueryNode) {
            $this->applyNot($expr, $boolean);
            return;
        }

        if ($expr instanceof BasicQueryNode) {
            $this->applyComparison($expr, $boolean, $wrap);
        }
    }

    protected function applyLogical(CompositeQueryNode $node, string $boolean, bool $wrap): void
    {
        $operator = strtolower($node->getOperator());
        $left = $node->getLeft();
        $right = $node->getRight();

        if ($operator === 'or') {
            $this->applyOrNode($left, $right, $boolean, $wrap);
            return;
        }

        $this->applyAndNode($left, $right, $boolean, $wrap);
    }

    protected function applyOrNode(QueryNode $left, QueryNode $right, string $boolean, bool $wrap = false): void
    {
        $method = $boolean === 'where' ? 'where' : $boolean;

        $this->builder->{$method}(function (Builder $q) use ($left, $right) {
            $exec = new self($this->query, $q, false, $this->lambdaAliases);
            $exec->execute($left, 'where');
            $exec->execute($right, 'orWhere');
        });
    }

    protected function applyAndNode(QueryNode $left, QueryNode $right, string $boolean, bool $wrap = false): void
    {
        $isLeftOr = $left instanceof CompositeQueryNode && strtolower($left->getOperator()) === 'or';
        $isRightOr = $right instanceof CompositeQueryNode && strtolower($right->getOperator()) === 'or';
        $requiresMixedGrouping = $this->shouldGroupMixedOperators($left, $right);
        $shouldGroup = $isLeftOr || $isRightOr || ($requiresMixedGrouping && $this->isRoot);
        $childWrap = $wrap || ($requiresMixedGrouping && $this->isRoot);

        if ($shouldGroup) {
            $method = $boolean === 'where' ? 'where' : $boolean;

            $this->builder->{$method}(function (Builder $q) use ($left, $right, $childWrap) {
                $exec = new self($this->query, $q, false, $this->lambdaAliases);
                $exec->execute($left, 'where', $childWrap);
                $exec->execute($right, 'where', $childWrap);
            });

            return;
        }

        $this->execute($left, $boolean, $childWrap);
        $this->execute($right, $boolean, $childWrap);
    }

    protected function applyComparison(BasicQueryNode $node, string $boolean, bool $wrap = false): void
    {
        [$transform, $field] = $this->extractTransform($node->getField());

        if (str_contains($field, '/')) {
            $segments = explode('/', $field);
            $first = array_shift($segments);

            if ($first === null) {
                return;
            }

            if (isset($this->lambdaAliases[$first])) {
                if (empty($segments)) {
                    return;
                }

                $strippedField = implode('/', $segments);
                $newField = $this->applyTransform($transform, $strippedField);
                $this->applyComparison(new BasicQueryNode($newField, $node->getOperator(), $node->getValue(), $node->isNegated()), $boolean, $wrap);

                return;
            }

            $remaining = implode('/', $segments);
            $newField = $this->applyTransform($transform, $remaining);
            $this->applyRelationComparison($first, new BasicQueryNode($newField, $node->getOperator(), $node->getValue(), $node->isNegated()), $boolean, $wrap);
            return;
        }

        $this->applyDirectFilter($this->applyTransform($transform, $field), $node, $boolean, $wrap);
    }

    protected function applyRelationComparison(string $relation, BasicQueryNode $node, string $boolean, bool $wrap): void
    {
        $relationName = $this->resolveRelationName($relation);
        if ($relationName === null) {
            return;
        }

        $method = $boolean === 'where' ? 'whereHas' : 'orWhereHas';
        if (!method_exists($this->builder, $method)) {
            // Fallback for builders without dynamic orWhereHas helpers
            $fallback = $boolean === 'where' ? 'where' : 'orWhere';
            $this->builder->{$fallback}(function (Builder $q) use ($relationName, $node, $wrap) {
                $q->whereHas($relationName, function (Builder $nested) use ($node, $wrap) {
                    $exec = new self($this->query, $nested, false, $this->lambdaAliases);
                    $inner = new BasicQueryNode($node->getField(), $node->getOperator(), $node->getValue(), $node->isNegated());
                    $exec->applyComparison($inner, 'where', $wrap);
                });
            });

            return;
        }

        $this->builder->{$method}($relationName, function (Builder $q) use ($node, $wrap) {
            $exec = new self($this->query, $q, false, $this->lambdaAliases);
            $inner = new BasicQueryNode($node->getField(), $node->getOperator(), $node->getValue(), $node->isNegated());
            $exec->applyComparison($inner, 'where', $wrap);
        });
    }

    protected function applyDirectFilter(string $field, BasicQueryNode $node, string $boolean, bool $wrap): void
    {
        [$transform, $baseField] = $this->extractTransform($field);

        $column = $this->resolveColumn($baseField);
        if ($column === false) {
            return;
        }

        $table = $this->builder->getModel()->getTable();
        $qualified = $table . '.' . $column;
        $grammar = $this->builder->getQuery()->getGrammar();
        $wrappedColumn = $grammar->wrap($qualified);

        if ($transform !== null) {
            $this->applyTransformedFilter($qualified, $transform, $node, $boolean);
            return;
        }

        $odataOperator = strtolower($node->getOperator());
        $negated = $node->isNegated();

        if ($this->isNullComparison($odataOperator, $node->getValue())) {
            $shouldBeNull = ($odataOperator === 'eq') !== $negated;

            if ($wrap) {
                $operator = $shouldBeNull ? 'IS NULL' : 'IS NOT NULL';
                $this->applyWrappedRaw($boolean, $wrappedColumn . ' ' . $operator);
                return;
            }

            $methodBase = $boolean === 'where' ? 'where' : 'orWhere';
            $method = $methodBase . ($shouldBeNull ? 'Null' : 'NotNull');
            $this->builder->{$method}($qualified);
            return;
        }

        if ($odataOperator === 'in') {
            $values = $this->formatInValues($node->getValue());

            if ($wrap) {
                $operator = $negated ? 'NOT IN' : 'IN';
                $placeholders = implode(', ', array_fill(0, count($values), '?'));
                $this->applyWrappedRaw($boolean, $wrappedColumn . " {$operator} ({$placeholders})", $values);
                return;
            }

            $methodBase = $boolean === 'where' ? 'where' : 'orWhere';
            $method = $methodBase . ($negated ? 'NotIn' : 'In');
            $this->builder->{$method}($qualified, $values);
            return;
        }

        $sqlOperator = $this->mapOperator($odataOperator, $negated);
        $value = $this->prepareComparisonValue($node);

        if (in_array($odataOperator, ['contains', 'startswith', 'endswith'], true)) {
            $this->applyWrappedRaw($boolean, $wrappedColumn . " {$sqlOperator} ?", [$value]);
            return;
        }

        if ($wrap) {
            $this->applyWrappedRaw($boolean, $wrappedColumn . " {$sqlOperator} ?", [$value]);
            return;
        }

        $this->builder->{$boolean}($qualified, $sqlOperator, $value);
    }

    protected function applyLambda(LambdaQueryNode $node, string $boolean): void
    {
        $relationName = $this->resolveRelationName($node->getRelation());
        if ($relationName === null) {
            return;
        }

        $aliases = $this->lambdaAliases;
        $parameter = $node->getParameter();
        if ($parameter !== null && $parameter !== '') {
            $aliases[$parameter] = true;
        }

        if ($node->getLambda() === 'any') {
            $method = $boolean === 'where' ? 'whereHas' : 'orWhereHas';
            if (!method_exists($this->builder, $method)) {
                $fallback = $boolean === 'where' ? 'where' : 'orWhere';
                $this->builder->{$fallback}(function (Builder $q) use ($relationName, $node, $aliases) {
                    $q->whereHas($relationName, function (Builder $subQuery) use ($node, $aliases) {
                        $exec = new self($this->query, $subQuery, false, $aliases);
                        $exec->execute($node->getCondition(), 'where');
                    });
                });

                return;
            }

            $this->builder->{$method}($relationName, function (Builder $subQuery) use ($node, $aliases) {
                $exec = new self($this->query, $subQuery, false, $aliases);
                $exec->execute($node->getCondition(), 'where');
            });

            return;
        }

        // Lambda 'all' => NOT EXISTS (violating rows)
        $method = $boolean === 'where' ? 'whereDoesntHave' : 'orWhereDoesntHave';
        $handler = function (Builder $subQuery) use ($node, $aliases) {
            $exec = new self($this->query, $subQuery, false, $aliases);
            $exec->execute($this->negateNode($node->getCondition()), 'where');
        };

        if (method_exists($this->builder, $method)) {
            $this->builder->{$method}($relationName, $handler);
            return;
        }

        $fallback = $boolean === 'where' ? 'where' : 'orWhere';
        $this->builder->{$fallback}(function (Builder $q) use ($relationName, $handler) {
            $q->whereDoesntHave($relationName, $handler);
        });
    }

    protected function applyNot(NotQueryNode $node, string $boolean): void
    {
        $innerBuilder = $this->builder->newQuery();
        $innerExecutor = new self($this->query, $innerBuilder, false, $this->lambdaAliases);
        $innerExecutor->execute($node->getInner(), 'where');

        $query = $innerBuilder->getQuery();
        if (empty($query->wheres)) {
            return;
        }

        $grammar = $query->getGrammar();
        $compiled = $grammar->compileWheres($query);
        $compiled = preg_replace('/^where\\s+/i', '', $compiled);
        $bindings = $query->bindings['where'] ?? [];

        $method = $boolean === 'where' ? 'whereRaw' : 'orWhereRaw';
        $this->builder->{$method}('not (' . $compiled . ')', $bindings);
    }

    protected function resolveRelationName(string $relation): ?string
    {
        $className = class_basename($this->builder->getModel());
        $resolved = $this->query->isPropertyExpandable($relation, $className);

        if ($resolved === false) {
            return null;
        }

        return $resolved;
    }

    protected function resolveColumn(string $field): string|bool
    {
        $className = class_basename($this->builder->getModel());
        $mapped = $this->query->isPropertyFilterable($field, $className);

        if ($mapped === true) {
            return $field;
        }

        return $mapped;
    }

    protected function mapOperator(string $operator, bool $negated = false): string
    {
        return match ($operator) {
            'eq' => $negated ? '!=' : '=',
            'ne' => $negated ? '=' : '!=',
            'gt' => $negated ? '<=' : '>',
            'ge' => $negated ? '<' : '>=',
            'lt' => $negated ? '>=' : '<',
            'le' => $negated ? '>' : '<=',
            'contains', 'startswith', 'endswith' => $negated ? 'NOT LIKE' : 'LIKE',
            'in' => $negated ? 'NOT IN' : 'IN',
            default => $operator,
        };
    }

    protected function formatInValues(mixed $value): array
    {
        if (is_string($value) && str_contains($value, '(') && str_contains($value, ')')) {
            $value = trim($value, '()');
            $items = array_map('trim', explode(',', $value));
        } else {
            $items = (array) $value;
        }

        return array_map(function ($item) {
            $normalized = $this->normalizeScalarValue($item);

            if (is_string($normalized) && strtolower($normalized) === 'null') {
                return null;
            }

            if (is_numeric($normalized) && $normalized !== '') {
                return str_contains((string) $normalized, '.') ? (float) $normalized : (int) $normalized;
            }

            return $normalized;
        }, $items);
    }

    protected function normalizeScalarValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        return trim($trimmed, "'");
    }

    protected function prepareComparisonValue(BasicQueryNode $node): mixed
    {
        $operator = strtolower($node->getOperator());
        $normalized = $this->normalizeScalarValue($node->getValue());

        return match ($operator) {
            'contains' => '%' . $normalized . '%',
            'startswith' => $normalized . '%',
            'endswith' => '%' . $normalized,
            default => $normalized,
        };
    }

    protected function isNullComparison(string $operator, mixed $value): bool
    {
        if (!in_array($operator, ['eq', 'ne'], true)) {
            return false;
        }

        $normalized = $this->normalizeScalarValue($value);
        return is_string($normalized) && strtolower($normalized) === 'null';
    }

    protected function negateNode(QueryNode $node): QueryNode
    {
        if ($node instanceof BasicQueryNode) {
            $operator = strtolower($node->getOperator());
            if (in_array($operator, ['contains', 'startswith', 'endswith'], true)) {
                return new NotQueryNode($node);
            }

            return $node->withNegated();
        }

        if ($node instanceof CompositeQueryNode) {
            $originalOperator = strtolower($node->getOperator());

            if ($originalOperator === 'or') {
                return new NotQueryNode($node);
            }

            $negatedLeft = $this->negateNode($node->getLeft());
            $negatedRight = $this->negateNode($node->getRight());

            return new CompositeQueryNode($negatedLeft, 'or', $negatedRight);
        }

        if ($node instanceof LambdaQueryNode) {
            $lambda = $node->getLambda() === 'any' ? 'all' : 'any';

            return new LambdaQueryNode(
                $node->getRelation(),
                $lambda,
                $this->negateNode($node->getCondition()),
                $node->getParameter()
            );
        }

        return $node;
    }

    protected function shouldGroupMixedOperators(QueryNode $left, QueryNode $right): bool
    {
        $leftHasFunction = $this->nodeHasFunctionOperator($left);
        $rightHasFunction = $this->nodeHasFunctionOperator($right);
        $leftHasNonFunction = $this->nodeHasNonFunctionOperator($left);
        $rightHasNonFunction = $this->nodeHasNonFunctionOperator($right);

        return ($leftHasFunction && $rightHasNonFunction) || ($rightHasFunction && $leftHasNonFunction);
    }

    protected function nodeHasFunctionOperator(QueryNode $node): bool
    {
        if ($node instanceof BasicQueryNode) {
            return in_array(strtolower($node->getOperator()), ['contains', 'startswith', 'endswith'], true);
        }

        if ($node instanceof CompositeQueryNode) {
            return $this->nodeHasFunctionOperator($node->getLeft()) || $this->nodeHasFunctionOperator($node->getRight());
        }

        if ($node instanceof NotQueryNode) {
            return $this->nodeHasFunctionOperator($node->getInner());
        }

        return false;
    }

    protected function nodeHasNonFunctionOperator(QueryNode $node): bool
    {
        if ($node instanceof BasicQueryNode) {
            return !in_array(strtolower($node->getOperator()), ['contains', 'startswith', 'endswith'], true);
        }

        if ($node instanceof CompositeQueryNode) {
            return $this->nodeHasNonFunctionOperator($node->getLeft()) || $this->nodeHasNonFunctionOperator($node->getRight());
        }

        if ($node instanceof NotQueryNode) {
            return $this->nodeHasNonFunctionOperator($node->getInner());
        }

        return false;
    }

    private function applyTransformedFilter(string $qualified, string $transform, BasicQueryNode $node, string $boolean): void
    {
        $odataOperator = strtolower($node->getOperator());
        $negated = $node->isNegated();
        $columnSql = $this->buildTransformedColumn($qualified, $transform);
        $method = $boolean === 'where' ? 'whereRaw' : 'orWhereRaw';

        if ($this->isNullComparison($odataOperator, $node->getValue())) {
            $shouldBeNull = ($odataOperator === 'eq') !== $negated;
            $operator = $shouldBeNull ? 'IS NULL' : 'IS NOT NULL';
            $this->builder->{$method}('(' . $columnSql . ' ' . $operator . ')');
            return;
        }

        if ($odataOperator === 'in') {
            $values = $this->formatInValues($node->getValue());
            $placeholders = implode(', ', array_fill(0, count($values), '?'));
            $operator = $negated ? 'NOT IN' : 'IN';
            $this->builder->{$method}('(' . $columnSql . " {$operator} ({$placeholders}))", $values);
            return;
        }

        $sqlOperator = $this->mapOperator($odataOperator, $negated);
        $value = $this->prepareComparisonValue($node);
        $this->builder->{$method}('(' . $columnSql . " {$sqlOperator} ?)" , [$value]);
    }

    private function extractTransform(string $field): array
    {
        if (preg_match('/^(tolower|toupper|lower|upper|trim)\((.+)\)$/i', $field, $matches)) {
            return [strtolower($matches[1]), $matches[2]];
        }

        return [null, $field];
    }

    private function applyTransform(?string $transform, string $field): string
    {
        return $transform ? $transform . '(' . $field . ')' : $field;
    }

    private function buildTransformedColumn(string $qualified, string $transform): string
    {
        $wrapped = $this->builder->getQuery()->getGrammar()->wrap($qualified);
        $function = match ($transform) {
            'tolower', 'lower' => 'LOWER',
            'toupper', 'upper' => 'UPPER',
            'trim' => 'TRIM',
            default => strtoupper($transform),
        };

        return $function . '(' . $wrapped . ')';
    }

    private function applyWrappedRaw(string $boolean, string $expression, array $bindings = []): void
    {
        $method = $boolean === 'where' ? 'whereRaw' : 'orWhereRaw';
        $this->builder->{$method}('(' . $expression . ')', $bindings);
    }
}
