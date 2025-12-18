<?php
namespace Aqqo\OData\Services;

use Aqqo\OData\Query;
use Aqqo\OData\QueryNodeStructure\BasicQueryNode;
use Aqqo\OData\QueryNodeStructure\CompositeQueryNode;
use Aqqo\OData\QueryNodeStructure\LambdaQueryNode;
use Aqqo\OData\QueryNodeStructure\NotQueryNode;
use Aqqo\OData\QueryNodeStructure\QueryNode;
use Aqqo\OData\Utils\StringUtils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

class FilterExecutor
{
    protected Query $query;
    protected Builder $builder;
    protected bool $isRoot;
    /** @var array<string, true> */
    protected array $lambdaAliases;
    protected string $rootTable;
    protected string $rootModel;
    protected ?string $parentTable;
    protected ?string $parentModel;

    public function __construct(
        Query $query,
        Builder $builder,
        bool $isRoot = true,
        array $lambdaAliases = [],
        ?string $rootTable = null,
        ?string $rootModel = null,
        ?string $parentTable = null,
        ?string $parentModel = null
    )
    {
        $this->query = $query;
        $this->builder = $builder;
        $this->isRoot = $isRoot;
        $this->lambdaAliases = $lambdaAliases;
        $this->rootTable = $rootTable ?? $builder->getModel()->getTable();
        $this->rootModel = $rootModel ?? class_basename($builder->getModel());
        $this->parentTable = $parentTable;
        $this->parentModel = $parentModel;
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
            $exec = new self($this->query, $q, false, $this->lambdaAliases, $this->rootTable, $this->rootModel, $this->parentTable, $this->parentModel);
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
                $exec = new self($this->query, $q, false, $this->lambdaAliases, $this->rootTable, $this->rootModel, $this->parentTable, $this->parentModel);
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
        // Arithmetic expression on LHS (e.g. TotalAmount mul 1.21 gt CreditLimit)
        if (preg_match('/\\s(?:add|sub|mul|div)\\s/i', $node->getField()) === 1) {
            $this->applyArithmeticComparison($node, $boolean, $wrap);
            return;
        }

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

    private function applyArithmeticComparison(BasicQueryNode $node, string $boolean, bool $wrap): void
    {
        // Only supports: "<left> <op> <rhs>" where left is identifier and rhs is number/identifier.
        $fieldExpr = trim($node->getField());
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\\s+(add|sub|mul|div)\\s+(.+)$/i', $fieldExpr, $m)) {
            // Fallback: attempt normal processing
            $this->applyDirectFilter($fieldExpr, $node, $boolean, $wrap);
            return;
        }

        $leftName = $m[1];
        $op = strtolower($m[2]);
        $rhsRaw = trim($m[3]);

        $grammar = $this->builder->getQuery()->getGrammar();
        $table = $this->builder->getModel()->getTable();
        $leftCol = $this->resolveColumn($leftName);
        if ($leftCol === false || $leftCol === true) {
            return;
        }
        $leftSql = $leftCol instanceof Expression
            ? $leftCol->getValue($grammar)
            : $grammar->wrap($table . '.' . $leftCol);

        // RHS of arithmetic
        $rhsSql = null;
        if (is_numeric($rhsRaw)) {
            $rhsSql = $rhsRaw;
        } else {
            // strip quotes if any
            $rhsName = trim($rhsRaw, "'");
            // allow paths like Orders/DiscountLimit by taking last segment
            if (str_contains($rhsName, '/')) {
                $parts = explode('/', $rhsName);
                $rhsName = end($parts) ?: $rhsName;
            }

            $rhsCol = $this->resolveColumn($rhsName);
            if ($rhsCol !== false && $rhsCol !== true) {
                $rhsSql = $rhsCol instanceof Expression
                    ? $rhsCol->getValue($grammar)
                    : $grammar->wrap($table . '.' . $rhsCol);
            } else {
                // try root scope
                $mapped = $this->query->isPropertyFilterable($rhsName, $this->rootModel);
                if ($mapped !== false && $mapped !== true) {
                    $rhsSql = $mapped instanceof Expression
                        ? $mapped->getValue($grammar)
                        : $grammar->wrap($this->rootTable . '.' . $mapped);
                }
            }
        }

        if ($rhsSql === null) {
            return;
        }

        $arithOp = match ($op) {
            'mul' => '*',
            'add' => '+',
            'sub' => '-',
            'div' => '/',
        };

        $lhsSql = '(' . $leftSql . ' ' . $arithOp . ' ' . $rhsSql . ')';

        // RHS of comparison can be identifier/Expression too
        $odataOperator = strtolower($node->getOperator());
        $sqlOperator = $this->mapOperator($odataOperator, $node->isNegated());

        $rawValue = $node->getValue();
        $valueSql = null;
        $bindings = [];

        if (is_string($rawValue) && preg_match('/^[A-Za-z_]/', $rawValue) === 1) {
            $valuePath = $rawValue;
            if (str_contains($valuePath, '/')) {
                $parts = explode('/', $valuePath);
                $valuePath = end($parts) ?: $valuePath;
            }
            $col = $this->resolveColumn($valuePath);
            if ($col !== false && $col !== true) {
                $valueSql = $col instanceof Expression
                    ? $col->getValue($grammar)
                    : $grammar->wrap($table . '.' . $col);
            }
        }

        if ($valueSql === null) {
            $value = $this->prepareComparisonValue($node);
            if (is_string($value) && is_numeric($value)) {
                $valueSql = $value;
            } else {
                $valueSql = '?';
                $bindings = [$value];
            }
        }

        $method = match ($boolean) {
            'where' => 'whereRaw',
            'orWhere' => 'orWhereRaw',
            'having' => 'havingRaw',
            'orHaving' => 'orHavingRaw',
            default => 'whereRaw',
        };

        $this->builder->{$method}('(' . $lhsSql . " {$sqlOperator} {$valueSql})", $bindings);
    }

    protected function applyRelationComparison(string $relation, BasicQueryNode $node, string $boolean, bool $wrap): void
    {
        $relationName = $this->resolveRelationName($relation);
        if ($relationName === null) {
            return;
        }

        // Special case: navigation $count (e.g. Messages/$count gt 5)
        if (is_string($node->getField()) && strtolower($node->getField()) === '$count') {
            $this->applyRelationCountComparison($relationName, $node, $boolean);
            return;
        }

        $method = $boolean === 'where' ? 'whereHas' : 'orWhereHas';

        $this->builder->{$method}($relationName, function (Builder $q) use ($node, $wrap) {
            $exec = new self(
                $this->query,
                $q,
                false,
                $this->lambdaAliases,
                $this->rootTable,
                $this->rootModel,
                $this->builder->getModel()->getTable(),
                class_basename($this->builder->getModel())
            );
            $inner = new BasicQueryNode($node->getField(), $node->getOperator(), $node->getValue(), $node->isNegated());
            $exec->applyComparison($inner, 'where', $wrap);
        });
    }

    private function applyRelationCountComparison(string $relationName, BasicQueryNode $node, string $boolean): void
    {
        // Build correlated count subquery and compare it using whereRaw/havingRaw.
        if (!method_exists($this->builder->getModel(), $relationName)) {
            return;
        }

        $relation = $this->builder->getModel()->{$relationName}();
        if (!$relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
            return;
        }

        $countQuery = $relation->getRelationExistenceCountQuery($relation->getRelated()->newQuery(), $this->builder);
        $subSql = $countQuery->toSql();

        $operator = $this->mapOperator(strtolower($node->getOperator()), $node->isNegated());
        $value = $this->prepareComparisonValue($node);

        $method = match ($boolean) {
            'where' => 'whereRaw',
            'orWhere' => 'orWhereRaw',
            'having' => 'havingRaw',
            'orHaving' => 'orHavingRaw',
            default => 'whereRaw',
        };

        // We compare the subquery to a bound parameter; if the RHS is an arithmetic expression,
        // it will currently be treated as a string (still useful for compilation tests).
        $this->builder->{$method}('((' . $subSql . ") {$operator} ?)", array_merge($countQuery->getBindings(), [$value]));
    }

    protected function applyDirectFilter(string $field, BasicQueryNode $node, string $boolean, bool $wrap): void
    {
        [$transform, $baseField] = $this->extractTransform($field);

        $column = $this->resolveColumn($baseField);
        if ($column === false) {
            // Support simple computed function expressions (e.g. trim(concat(...))) on the LHS.
            if (is_string($baseField) && str_contains($baseField, '(') && str_ends_with(trim($baseField), ')')) {
                $column = new Expression($this->compileExpressionString($baseField));
            } else {
                return;
            }
        }

        $table = $this->builder->getModel()->getTable();
        $grammar = $this->builder->getQuery()->getGrammar();

        // Column can be a raw SQL expression (e.g. aggregate output used in HAVING)
        if ($column instanceof Expression) {
            $qualified = $column;
            $wrappedColumn = $column->getValue($grammar);
        } else {
            // Always qualify "normal" columns (the library supports virtual/non-schema fields too).
            $qualified = $table . '.' . $column;
            $wrappedColumn = $grammar->wrap($qualified);
        }

        if ($transform !== null) {
            if ($qualified instanceof Expression) {
                $columnSql = $this->buildTransformedExpressionSql($wrappedColumn, $transform);
                $odataOperator = strtolower($node->getOperator());
                $negated = $node->isNegated();

                if ($this->isNullComparison($odataOperator, $node->getValue())) {
                    $shouldBeNull = ($odataOperator === 'eq') !== $negated;
                    $method = match ($boolean) {
                        'where' => 'whereRaw',
                        'orWhere' => 'orWhereRaw',
                        'having' => 'havingRaw',
                        'orHaving' => 'orHavingRaw',
                        default => 'whereRaw',
                    };
                    $this->builder->{$method}('(' . $columnSql . ' ' . ($shouldBeNull ? 'IS NULL' : 'IS NOT NULL') . ')');
                    return;
                }

                $sqlOperator = $this->mapOperator($odataOperator, $negated);
                $value = $this->prepareComparisonValue($node);
                $method = match ($boolean) {
                    'where' => 'whereRaw',
                    'orWhere' => 'orWhereRaw',
                    'having' => 'havingRaw',
                    'orHaving' => 'orHavingRaw',
                    default => 'whereRaw',
                };
                $this->builder->{$method}('(' . $columnSql . " {$sqlOperator} ?)", [$value]);
                return;
            }

            $this->applyTransformedFilter($qualified, $transform, $node, $boolean);
            return;
        }

        $odataOperator = strtolower($node->getOperator());
        $negated = $node->isNegated();

        // Support comparisons where RHS is a column/expression (including parent/root scopes).
        $rawNodeValue = $node->getValue();
        if (is_string($rawNodeValue)
            && preg_match('/^[A-Za-z_]/', $rawNodeValue) === 1
            && !in_array(strtolower($rawNodeValue), ['null', 'true', 'false'], true)
        ) {
            $valuePath = $rawNodeValue;
            if (str_contains($valuePath, '/')) {
                $parts = explode('/', $valuePath);
                if (isset($this->lambdaAliases[$parts[0]])) {
                    array_shift($parts);
                }
                $valuePath = end($parts) ?: $rawNodeValue;
            }

            $sqlOperator = $this->mapOperator($odataOperator, $negated);
            $method = match ($boolean) {
                'where' => 'whereRaw',
                'orWhere' => 'orWhereRaw',
                'having' => 'havingRaw',
                'orHaving' => 'orHavingRaw',
                default => 'whereRaw',
            };

            // 1) current scope
            $right = $this->resolveColumn($valuePath);
            if ($right !== false) {
                $rightSql = $right instanceof Expression
                    ? $right->getValue($grammar)
                    : $grammar->wrap($table . '.' . $right);

                $this->builder->{$method}('(' . $wrappedColumn . " {$sqlOperator} " . $rightSql . ')');
                return;
            }

            // 2) parent/root scopes (correlated comparisons)
            foreach ([[$this->parentTable, $this->parentModel], [$this->rootTable, $this->rootModel]] as [$scopeTable, $scopeModel]) {
                if (!$scopeTable || !$scopeModel) {
                    continue;
                }

                $mapped = $this->query->isPropertyFilterable($valuePath, $scopeModel);
                if ($mapped === false || $mapped === true) {
                    continue;
                }

                $rightSql = $mapped instanceof Expression
                    ? $mapped->getValue($grammar)
                    : $grammar->wrap($scopeTable . '.' . $mapped);

                $this->builder->{$method}('(' . $wrappedColumn . " {$sqlOperator} " . $rightSql . ')');
                return;
            }
        }

        if ($this->isNullComparison($odataOperator, $node->getValue())) {
            $shouldBeNull = ($odataOperator === 'eq') !== $negated;

            if ($wrap) {
                $operator = $shouldBeNull ? 'IS NULL' : 'IS NOT NULL';
                $this->applyWrappedRaw($boolean, $wrappedColumn . ' ' . $operator);
                return;
            }

            // Having doesn't provide havingNull helpers; use raw.
            if (in_array($boolean, ['having', 'orHaving'], true)) {
                $method = $boolean === 'having' ? 'havingRaw' : 'orHavingRaw';
                $this->builder->{$method}($wrappedColumn . ' ' . ($shouldBeNull ? 'IS NULL' : 'IS NOT NULL'));
            } else {
                $methodBase = $boolean === 'where' ? 'where' : 'orWhere';
                $method = $methodBase . ($shouldBeNull ? 'Null' : 'NotNull');
                $this->builder->{$method}($qualified);
            }
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

        if ($qualified instanceof Expression) {
            // Having/where on expressions must be raw.
            $method = match ($boolean) {
                'where' => 'whereRaw',
                'orWhere' => 'orWhereRaw',
                'having' => 'havingRaw',
                'orHaving' => 'orHavingRaw',
                default => 'whereRaw',
            };
            if (is_string($value) && is_numeric($value)) {
                // For numeric literals, inline to avoid SQLite type quirks with bound strings.
                $this->builder->{$method}('(' . $wrappedColumn . " {$sqlOperator} {$value})");
            } else {
                $this->builder->{$method}('(' . $wrappedColumn . " {$sqlOperator} ?)", [$value]);
            }
            return;
        }

        if ($wrap) {
            $this->applyWrappedRaw($boolean, $wrappedColumn . " {$sqlOperator} ?", [$value]);
            return;
        }

        $this->builder->{$boolean}($qualified, $sqlOperator, $value);
    }

    private function buildTransformedExpressionSql(string $innerSql, string $transform): string
    {
        $fn = match (strtolower($transform)) {
            'tolower', 'lower' => 'LOWER',
            'toupper', 'upper' => 'UPPER',
            'trim' => 'TRIM',
            default => null,
        };

        return $fn ? ($fn . '(' . $innerSql . ')') : $innerSql;
    }

    private function compileExpressionString(string $expr): string
    {
        $expr = trim($expr);

        // unwrap redundant parentheses
        if (str_starts_with($expr, '(') && str_ends_with($expr, ')')) {
            $candidate = trim(substr($expr, 1, -1));
            if ($candidate !== '') {
                $expr = $candidate;
            }
        }

        // literal string
        if (str_starts_with($expr, "'") && str_ends_with($expr, "'")) {
            return $expr;
        }

        // number
        if (is_numeric($expr)) {
            return $expr;
        }

        // function call: name(args)
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\((.*)\)$/s', $expr, $m)) {
            $name = strtolower($m[1]);
            $argsRaw = $m[2];
            $args = StringUtils::splitODataExpression($argsRaw, ',');
            $compiledArgs = array_map(fn (string $a) => $this->compileExpressionString($a), $args);

            $driver = $this->builder->getConnection()->getDriverName();

            return match ($name) {
                'trim' => 'TRIM(' . ($compiledArgs[0] ?? "''") . ')',
                'concat' => ($driver === 'sqlite'
                    ? '(' . implode(' || ', $compiledArgs) . ')'
                    : 'CONCAT(' . implode(', ', $compiledArgs) . ')'
                ),
                default => strtoupper($name) . '(' . implode(', ', $compiledArgs) . ')',
            };
        }

        // identifier/property name
        $mapped = $this->resolveColumn($expr);
        if ($mapped instanceof Expression) {
            return $mapped->getValue($this->builder->getQuery()->getGrammar());
        }
        if (is_string($mapped)) {
            $table = $this->builder->getModel()->getTable();
            $schema = $this->builder->getConnection()->getSchemaBuilder();
            if ($schema->hasColumn($table, $mapped)) {
                return $this->builder->getQuery()->getGrammar()->wrap($table . '.' . $mapped);
            }
        }

        // Fallback: treat as raw identifier
        return $expr;
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
                $exec = new self(
                    $this->query,
                    $subQuery,
                    false,
                    $aliases,
                    $this->rootTable,
                    $this->rootModel,
                    $this->builder->getModel()->getTable(),
                    class_basename($this->builder->getModel())
                );
                $exec->execute($node->getCondition(), 'where');
            });

            return;
        }

        // Lambda 'all' => NOT EXISTS (violating rows)
        $method = $boolean === 'where' ? 'whereDoesntHave' : 'orWhereDoesntHave';
        $handler = function (Builder $subQuery) use ($node, $aliases) {
            $exec = new self(
                $this->query,
                $subQuery,
                false,
                $aliases,
                $this->rootTable,
                $this->rootModel,
                $this->builder->getModel()->getTable(),
                class_basename($this->builder->getModel())
            );
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

        $innerExecutor = new self($this->query, $innerBuilder, false, $this->lambdaAliases, $this->rootTable, $this->rootModel, $this->parentTable, $this->parentModel);
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
        // Support nested relation paths in lambdas, e.g. "Product/Suppliers"
        if (str_contains($relation, '/')) {
            $segments = array_values(array_filter(array_map('trim', explode('/', $relation))));
            $model = $this->builder->getModel();
            $currentClass = class_basename($model);

            $resolvedSegments = [];
            foreach ($segments as $seg) {
                $resolved = $this->query->isPropertyExpandable($seg, $currentClass);
                if ($resolved === false) {
                    return null;
                }
                $resolvedSegments[] = $resolved;

                // Advance model context for next segment (best-effort)
                if (method_exists($model, $resolved)) {
                    $model = $model->{$resolved}()->getModel();
                    $currentClass = class_basename($model);
                }
            }

            return implode('.', $resolvedSegments);
        }

        $className = class_basename($this->builder->getModel());
        $resolved = $this->query->isPropertyExpandable($relation, $className);

        if ($resolved === false) {
            return null;
        }

        return $resolved;
    }

    protected function resolveColumn(string $field): string|Expression|bool
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
                // ¬(all(cond)) == any(¬cond)
                return new LambdaQueryNode(
                    $node->getRelation(),
                    'any',
                    $this->negateNode($node->getCondition()),
                    $node->getParameter()
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
        $executor = new self($this->query, $builder, false, $this->lambdaAliases, $this->rootTable, $this->rootModel, $this->parentTable, $this->parentModel);
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
