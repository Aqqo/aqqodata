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
            $this->applyLogical($expr, $boolean, $wrap || $expr->isGrouped());
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

        if ($operator === 'or') {
            $this->applyOrNode($node, $boolean, $wrap);
            return;
        }

        $this->applyAndNode($node, $boolean, $wrap);
    }

    protected function applyOrNode(CompositeQueryNode $node, string $boolean, bool $wrap = false): void
    {
        $left = $node->getLeft();
        $right = $node->getRight();

        if ($boolean === 'where' && !$wrap && $this->isRoot) {
            [$leftSql, $leftBindings, $leftValid, $leftGroup] = $this->buildNodeExpression($left);
            [$rightSql, $rightBindings, $rightValid, $rightGroup] = $this->buildNodeExpression($right);

            if ($leftValid && $rightValid) {
                $expression = '';
                $expression .= $leftGroup ? $this->ensureWrapped($leftSql) : $leftSql;
                $expression .= ' or ';
                $expression .= $rightGroup ? $this->ensureWrapped($rightSql) : $rightSql;
                $wrapExpression = !($leftGroup || $rightGroup);
                $this->applyWrappedRaw('where', $expression, array_merge($leftBindings, $rightBindings), true, $wrapExpression);
                return;
            }

            $this->execute($left, 'where', true);
            $this->execute($right, 'orWhere', true);
            return;
        }

        $method = $boolean === 'where' ? 'where' : $boolean;
        $this->builder->{$method}(function (Builder $q) use ($left, $right, $wrap) {
            $exec = new self($this->query, $q, false, $this->lambdaAliases);
            $exec->execute($left, 'where');
            $exec->execute($right, 'orWhere');
        });
    }

    protected function applyAndNode(CompositeQueryNode $node, string $boolean, bool $wrap = false): void
    {
        $left = $node->getLeft();
        $right = $node->getRight();

        $isLeftOr = $left instanceof CompositeQueryNode && strtolower($left->getOperator()) === 'or';
        $isRightOr = $right instanceof CompositeQueryNode && strtolower($right->getOperator()) === 'or';
        $requiresMixedGrouping = $this->shouldGroupMixedOperators($left, $right);
        $shouldGroup = $wrap || $isLeftOr || $isRightOr || ($requiresMixedGrouping && $this->isRoot);
        $childWrap = $requiresMixedGrouping;

        if ($shouldGroup) {
            $method = $boolean === 'where' ? 'where' : $boolean;

            $this->builder->{$method}(function (Builder $q) use ($left, $right, $childWrap) {
                $exec = new self($this->query, $q, false, $this->lambdaAliases);
                $exec->execute($left, 'where', $childWrap);
                $exec->execute($right, 'where', $childWrap);
            });

            return;
        }

        $this->execute($left, $boolean);
        $this->execute($right, $boolean);
    }

    protected function applyComparison(BasicQueryNode $node, string $boolean, bool $wrap = false): void
    {
        [$transform, $field] = $this->extractTransform($node->getField());

        if (str_contains($field, '/')) {
            $segments = explode('/', $field);
            $first = array_shift($segments);

            if (isset($this->lambdaAliases[$first])) {
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

            // Empty IN array should return all results (no filter applied)
            if (empty($values)) {
                return;
            }

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

        // Empty string value should return all results (no filter applied) for non-IN operators
        if (is_string($value) && $value === '' && !in_array($odataOperator, ['contains', 'startswith', 'endswith'], true)) {
            return;
        }

        if (in_array($odataOperator, ['contains', 'startswith', 'endswith'], true)) {
            $this->applyWrappedRaw($boolean, $wrappedColumn . " {$sqlOperator} ?", [$value], false);
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
        $relation = $node->getRelation();
        if (str_contains($relation, '/')) {
            $segments = explode('/', $relation);
            $first = array_shift($segments);
            if ($first !== null && isset($this->lambdaAliases[$first])) {
                $relation = implode('/', $segments);
            }
        }

        $relationName = $this->resolveRelationName($relation);
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

        $this->builder->{$method}($relationName, $handler);
    }

    protected function applyNot(NotQueryNode $node, string $boolean): void
    {
        $innerBuilder = $this->builder->getModel()->newQuery();
        $query = $innerBuilder->getQuery();
        $baseWhereCount = count($query->wheres ?? []);
        $baseWhereBindingsCount = count($query->bindings['where'] ?? []);

        $innerExecutor = new self($this->query, $innerBuilder, false, $this->lambdaAliases);
        $innerExecutor->execute($node->getInner(), 'where');

        $query = $innerBuilder->getQuery();
        if (empty($query->wheres)) {
            return;
        }

        $grammar = $query->getGrammar();
        // Trim base relation constraints
        if ($baseWhereCount > 0 && isset($query->wheres)) {
            $query->wheres = array_slice($query->wheres, $baseWhereCount);
        }

        if ($baseWhereBindingsCount > 0 && isset($query->bindings['where'])) {
            $query->bindings['where'] = array_slice($query->bindings['where'], $baseWhereBindingsCount);
        }

        $compiled = $grammar->compileWheres($query);
        $compiled = preg_replace('/^where\s+/i', '', $compiled) ?? '';
        $compiled = $this->stripRedundantParentheses($compiled);
        $bindings = $query->bindings['where'] ?? [];

        $method = $boolean === 'where' ? 'whereRaw' : 'orWhereRaw';
        $trimmedCompiled = ltrim($compiled);
        if (str_starts_with($trimmedCompiled, 'exists (')) {
            $expression = 'not ' . $trimmedCompiled;
        } else {
            $expression = 'not (' . $trimmedCompiled . ')';
        }

        $this->builder->{$method}($expression, $bindings);
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
        // Skip invalid fields (used for invalid filter syntax)
        if ($field === '__invalid__') {
            return false;
        }

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

        if (is_string($normalized)) {
            $lower = strtolower($normalized);
            if ($lower === 'true') {
                $normalized = '1';
            } elseif ($lower === 'false') {
                $normalized = '0';
            }
        }

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
            return $node->withNegated();
        }

        if ($node instanceof CompositeQueryNode) {
            $originalOperator = strtolower($node->getOperator());

            if ($originalOperator === 'or' && !$this->canDistributeNegationForOr($node)) {
                return new NotQueryNode($node);
            }

            $negatedLeft = $this->negateNode($node->getLeft());
            $negatedRight = $this->negateNode($node->getRight());
            $newOperator = $originalOperator === 'and' ? 'or' : 'and';

            return new CompositeQueryNode($negatedLeft, $newOperator, $negatedRight);
        }

        if ($node instanceof LambdaQueryNode) {
            if ($node->getLambda() === 'all') {
                return new NotQueryNode(
                    new LambdaQueryNode(
                        $node->getRelation(),
                        'any',
                        $node->getCondition(),
                        $node->getParameter()
                    )
                );
            }

            return new LambdaQueryNode(
                $node->getRelation(),
                'all',
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
            
            // Empty IN array should return all results (no filter applied)
            if (empty($values)) {
                return;
            }
            
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
        };

        return $function . '(' . $wrapped . ')';
    }

    private function applyWrappedRaw(string $boolean, string $expression, array $bindings = [], bool $useBindings = true, bool $wrapExpression = true): void
    {
        if (!$useBindings && !empty($bindings)) {
            foreach ($bindings as $binding) {
                $expression = preg_replace('/\?/', $this->escapeLiteral($binding), $expression, 1);
            }
            $bindings = [];
        }

        $method = $boolean === 'where' ? 'whereRaw' : 'orWhereRaw';
        $final = $wrapExpression ? '(' . $expression . ')' : $expression;
        $this->builder->{$method}($final, $bindings);
    }

    private function canDistributeNegationForOr(CompositeQueryNode $node): bool
    {
        return $this->isComparisonOnly($node->getLeft()) && $this->isComparisonOnly($node->getRight());
    }

    private function isComparisonOnly(QueryNode $node): bool
    {
        if ($node instanceof BasicQueryNode) {
            return !in_array(strtolower($node->getOperator()), ['contains', 'startswith', 'endswith'], true);
        }

        if ($node instanceof CompositeQueryNode) {
            return $this->isComparisonOnly($node->getLeft()) && $this->isComparisonOnly($node->getRight());
        }

        if ($node instanceof LambdaQueryNode) {
            return true;
        }

        if ($node instanceof NotQueryNode) {
            return $this->isComparisonOnly($node->getInner());
        }

        return false;
    }

    private function stripRedundantParentheses(string $expression): string
    {
        $trimmed = trim($expression);
        while (str_starts_with($trimmed, '(') && str_ends_with($trimmed, ')')) {
            $candidate = trim(substr($trimmed, 1, -1));
            if ($candidate === '') {
                break;
            }

            if (!$this->isBalancedParentheses($candidate)) {
                break;
            }

            $trimmed = $candidate;
        }

        return $trimmed;
    }

    private function isBalancedParentheses(string $expression): bool
    {
        $depth = 0;
        $length = strlen($expression);

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }

    private function escapeLiteral(mixed $value): string
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        if (is_null($value)) {
            return 'null';
        }

        $stringValue = (string)$value;
        $escaped = addslashes($stringValue);

        return "'" . $escaped . "'";
    }

    /**
     * @return array{0:string,1:array<int,mixed>,2:bool,3:bool}
     */
    private function buildBasicExpression(BasicQueryNode $node): array
    {
        [$transform, $baseField] = $this->extractTransform($node->getField());
        if ($transform !== null) {
            return ['', [], false, false];
        }

        $column = $this->resolveColumn($baseField);
        if ($column === false) {
            return ['', [], false, false];
        }

        $table = $this->builder->getModel()->getTable();
        $qualified = $table . '.' . $column;
        $wrapped = $this->builder->getQuery()->getGrammar()->wrap($qualified);

        $odataOperator = strtolower($node->getOperator());
        $negated = $node->isNegated();

        if ($this->isNullComparison($odataOperator, $node->getValue())) {
            $shouldBeNull = ($odataOperator === 'eq') !== $negated;
            $sql = $wrapped . ' ' . ($shouldBeNull ? 'IS NULL' : 'IS NOT NULL');
            return [$sql, [], true, false];
        }

        if ($odataOperator === 'in') {
            $values = $this->formatInValues($node->getValue());
            $placeholders = implode(', ', array_fill(0, count($values), '?'));
            $sql = $wrapped . ' ' . ($negated ? 'NOT IN' : 'IN') . ' (' . $placeholders . ')';
            return [$sql, $values, true, false];
        }

        $sqlOperator = $this->mapOperator($odataOperator, $negated);
        $value = $this->prepareComparisonValue($node);

        return [$wrapped . " {$sqlOperator} ?", [$value], true, false];
    }

    /**
     * @return array{0:string,1:array<int,mixed>,2:bool,3:bool}
     */
    private function buildNodeExpression(QueryNode $node): array
    {
        if ($node instanceof BasicQueryNode) {
            return $this->buildBasicExpression($node);
        }

        $builder = $this->builder->getModel()->newQuery();
        $executor = new self($this->query, $builder, false, $this->lambdaAliases);
        $executor->execute($node, 'where');

        $query = $builder->getQuery();
        if (empty($query->wheres)) {
            return ['', [], false];
        }

        $grammar = $query->getGrammar();
        $compiled = $grammar->compileWheres($query);
        $compiled = preg_replace('/^where\s+/i', '', $compiled) ?? '';

        return [$compiled, $query->bindings['where'] ?? [], true, true];
    }

    private function ensureWrapped(string $expression): string
    {
        $trimmed = trim($expression);
        if (str_starts_with($trimmed, '(') && str_ends_with($trimmed, ')')) {
            return $trimmed;
        }

        return '(' . $trimmed . ')';
    }
}
