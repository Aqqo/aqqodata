<?php

namespace Aqqo\OData\Traits;

use Aqqo\OData\Utils\ClassUtils;
use Aqqo\OData\Utils\OperatorUtils;
use Aqqo\OData\Utils\StringUtils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * @template TModelClass of Model
 * @template TRelatedModel of Model
 */
trait FilterTrait
{
    /**
     * @return void
     * @throws \ReflectionException
     */
    public function addFilters(): void
    {
        $filter = $this->request?->input('$filter');

        if (empty($filter)) {
            preg_match('/\(([^)]+)\)/', $this->request->url(), $matches);
            if (!empty($matches[1])) {
                $filter = "{$this->subject->getModel()->getKeyName()} eq '{$matches[1]}'";
            } else {
                return;
            }
        }

        $this->appendFilterQuery(strval($filter), $this->subject);
    }

    /**
     * Append filter queries to the builder based on the OData filter string.
     *
     * @param string $filter
     * @param Builder<TModelClass> $builder
     * @param string $statement
     * @return void
     * @throws \ReflectionException
     */
    public function appendFilterQuery(string $filter, Builder $builder, string $statement = 'where'): void
    {
        $expressions = StringUtils::splitODataExpression($filter);

        if ($this->hasNestedExpressions($filter, $expressions)) {
            $this->applyNestedExpressions($expressions, $builder, $statement);
        } else {
            $this->applySimpleExpressions($filter, $builder);
        }
    }

    /**
     * Determine if the filter contains nested expressions.
     *
     * @param string $filter
     * @param array<string> $expressions
     * @return bool
     */
    private function hasNestedExpressions(string $filter, array $expressions): bool
    {
        return (str_contains($filter, '(') || str_contains($filter, ')')) && count($expressions) > 1;
    }

    /**
     * Apply nested expressions to the builder.
     *
     * @param array<string> $expressions
     * @param Builder<TModelClass> $builder
     * @param string $statement
     * @return void
     * @throws \ReflectionException
     */
    private function applyNestedExpressions(array $expressions, Builder $builder, string $statement): void
    {
        $builder->where(function (Builder $query) use ($expressions, $statement) {
            foreach ($expressions as $value) {
                $value = trim($value);
                if (in_array($value, ['and', 'or'], true)) {
                    $statement = $value === 'or' ? 'orWhere' : 'where';
                    continue;
                }

                $query->{$statement}(function (Builder $subQuery) use ($value, $statement) {
                    $value = $this->stripParentheses($value);
                    $this->appendFilterQuery($value, $subQuery, $statement);
                });
            }
        });
    }

    /**
     * Apply simple expressions to the builder.
     *
     * @param string $filter
     * @param Builder<TModelClass> $builder
     * @return void
     * @throws \ReflectionException
     */
    private function applySimpleExpressions(string $filter, Builder $builder): void
    {
        $filter = $this->stripParentheses($filter);
        $groupedFilters = preg_split('/\s+(and|or)\s+/i', $filter, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if (!$groupedFilters) {
            return;
        }

        $currentStatement = 'where';

        foreach ($groupedFilters as $filterPart) {
            $filterPart = trim($filterPart);

            if (in_array(strtolower($filterPart), ['and', 'or'], true)) {
                $currentStatement = strtolower($filterPart) === 'or' ? 'orWhere' : 'where';
                continue;
            }

            if ($this->isAggregateFunction($filterPart)) {
                $this->handleAggregateFunction($filterPart, $builder, $currentStatement);
                continue;
            }

            [$column, $operator, $value, $lambda, $relation] = $this->splitInput($filterPart);

            if ($lambda) {
                if ($expandable = $this->isPropertyExpandable($relation, ClassUtils::getShortName($builder->getModel()))) {
                    if ($lambda == 'any') {
                        $function = ($currentStatement == 'or') ? 'orWhereHas' : 'whereHas';
                    } else {
                        $function = ($currentStatement == 'or') ? 'orWhereDoesntHave' : 'whereDoesntHave';
                    }

                    $builder->{$function}($expandable, function ($query) use ($column, $operator, $value) {

                        if ($column = $this->isValidFilter($column, $operator, $value, $query)) {
                            $query->where($column, $operator, $value);
                        }

                    });
                }
                continue;
            }

            if (!$column = $this->isValidFilter($column, $operator, $value, $builder)) {
                continue;
            }

            $builder->{$currentStatement}($column, $operator, $value);
        }
    }

    /**
     * Strip leading and trailing parentheses from a string.
     *
     * @param string $value
     * @return string
     */
    private function stripParentheses(string $value): string
    {
        return (str_starts_with($value, '(') && str_ends_with($value, ')'))
            ? substr($value, 1, -1)
            : $value;
    }

    /**
     * Check if the filter part is an aggregate function (any/all).
     *
     * @param string $filterPart
     * @return bool
     */
    private function isAggregateFunction(string $filterPart): bool
    {
        return str_starts_with($filterPart, 'any(') || str_starts_with($filterPart, 'all(');
    }

    /**
     * Handle aggregate functions (any/all) in the filter.
     *
     * @param string $filterPart
     * @param Builder<TModelClass> $builder
     * @param string $statement
     * @return void
     */
    private function handleAggregateFunction(string $filterPart, Builder $builder, string $statement): void
    {
        if (!preg_match('/^(any|all)\((\w+),\s*(.+)\)$/i', $filterPart, $matches)) {
            return;
        }

        [$_, $function, $relation, $condition] = $matches;

        $this->applyRelationshipCondition($builder, strtolower($function), $relation, $condition);
    }

    /**
     * Validate the filter components before applying to the builder.
     *
     * @param string $column
     * @param string $operator
     * @param string $value
     * @param Builder<TModelClass> $builder
     * @return bool
     * @throws \ReflectionException
     */
    private function isValidFilter(string $column, string $operator, string $value, Builder $builder): string|bool
    {
        if (empty($column) || empty($operator) || ($value != 0 && empty($value))) {
            return false;
        }

        $modelClass = get_class($builder->getModel());
        $reflection = new ReflectionClass($modelClass);
        $shortName = $reflection->getShortName();

        return $this->isPropertyFilterable($column, $shortName);
    }

    /**
     * Apply relationship conditions (any/all or regular relationships) to the builder.
     *
     * @param Builder<TModelClass> $builder
     * @param string $function
     * @param string $relation
     * @param string $condition
     * @return void
     */
    protected function applyRelationshipCondition(Builder $builder, string $function, string $relation, string $condition): void
    {
        $condition = trim($condition, '()');

        [$column, $operator, $value] = $this->splitInput($condition, $function === 'all');

        if (!$this->isPropertyFilterable("{$relation}.{$column}")) {
            return;
        }

        $expandable = $this->isPropertyExpandable($relation);

        if (!$expandable) {
            return;
        }

        $method = ($function === 'all' ? 'whereDoesntHave' : 'whereHas');

        $builder->{$method}($expandable, function (Builder $q) use ($column, $operator, $value) {
            $q->where($column, $operator, $value);
        });
    }

    /**
     * Split the input filter string into column, operator, and value.
     *
     * @param string $input
     * @param bool $inverseOperator
     * @return array<int, string>
     */
    private function splitInput(string $input, bool $inverseOperator = false): array
    {
        // Define the regex pattern to match tokens:
        // 1. Functions and operators
        // 2. Parentheses and commas
        // 3. String literals enclosed in single quotes
        // 4. Numeric values
        // 5. Field names or identifiers
        $pattern = '/\b(contains|startswith|endswith|and|or|not|eq|ne|gt|ge|lt|le)\b|([(),])|\'([^\']*)\'|(\d+(\.\d+)?)|([A-Za-z_][A-Za-z0-9_]*)/i';
        $lambda = null;
        $relation = null;
        // Perform global matching
        preg_match_all($pattern, $input, $matches, PREG_SET_ORDER);

        $tokens = array_map(function ($match) {
            if (!empty($match[1])) {
                // Functions and operators (e.g., contains, eq, and)
                return strtolower($match[1]);
            } elseif (!empty($match[3])) {
                // String literals without quotes
                return $match[3];
            } elseif (isset($match[4]) && is_numeric($match[4])) {
                // Numeric values
                return $match[4];
            } elseif (!empty($match[6])) {
                // Field names or identifiers
                return $match[6];
            }
            return null;
        }, $matches);

        $tokens = array_filter($tokens, fn($token) => $token !== null);

        if (count($tokens) < 3) {
            return ['', '', ''];
        }

        // Corrected logic: Check tokens[0] for function-based operators
        if (in_array($tokens[0], ['contains', 'startswith', 'endswith'], true)) {
            $column = $tokens[2];
            $operator = OperatorUtils::mapOperator($tokens[0], $inverseOperator);
            $value = OperatorUtils::getValueBasedOnOperator($tokens[0], $tokens[4]);
        } else if (isset($tokens[1]) && ($tokens[1] == 'any' || $tokens[1] == 'all')) {
            // Fixes for contains, startswith, endswith etc.
            if (isset($tokens[5]) && !isset($tokens[6])) {
                $tokens[6] = $tokens[5];
                $tokens[5] = $tokens[7];
                $tokens[7] = $tokens[9];
            }

            if (!isset($tokens[6])) {
                throw new \Exception('Invalid syntax');
            }
            $column = $tokens[5];
            $operator = OperatorUtils::mapOperator($tokens[6], ($tokens[1] == 'all'));
            $value = OperatorUtils::getValueBasedOnOperator($tokens[6], $tokens[7]);
            $lambda = $tokens[1];
            $relation = $tokens[0];
        } else {
            $column = $tokens[0];
            $operator = OperatorUtils::mapOperator($tokens[1], $inverseOperator);
            $value = OperatorUtils::getValueBasedOnOperator($tokens[1], $tokens[2]);
        }

        return [
            $column,
            $operator,
            $value,
            $lambda,
            $relation
        ];
    }
}
