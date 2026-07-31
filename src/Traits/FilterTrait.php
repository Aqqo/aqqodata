<?php

namespace Aqqo\OData\Traits;

use Aqqo\OData\Exceptions\QueryException;
use Aqqo\OData\Utils\ClassUtils;
use Aqqo\OData\Utils\OperatorUtils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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
            preg_match('/\(([^)]+)\)/', $this->request?->url() ?? '', $matches);
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
        $expressions = $this->splitFilter($filter);

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
        $model = $builder->getModel();
        $modelTable = $model->getTable();

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

            /**
             * @var string $column
             * @var string $operator
             * @var string|array|null $value
             * @var string $lambda
             * @var string $relation
             */
            [$column, $operator, $value, $lambda, $relation] = $this->splitInput($filterPart);

            if ($lambda) {
                if ($expandable = $this->isPropertyExpandable($relation, ClassUtils::getShortName($builder->getModel()))) {
                    if ($lambda == 'any') {
                        $function = ($currentStatement == 'or') ? 'orWhereHas' : 'whereHas';
                    } else {
                        $function = ($currentStatement == 'or') ? 'orWhereDoesntHave' : 'whereDoesntHave';
                    }

                    $builder->{$function}($expandable, function ($query) use ($column, $operator, $value) {
                        $resolved = $this->isValidFilter($column, $operator, $value, $query);

                        if ($resolved === false) {
                            if ($this->strict && $column !== '' && $this->isPropertyFilterable($column, ClassUtils::getShortName($query->getModel())) === false) {
                                throw new QueryException("Invalid \$filter condition on '{$column}'. The property is unknown or not filterable on the expanded relation.");
                            }

                            return;
                        }

                        $this->applyFilterCondition($query, 'where', $resolved, $operator, $value);
                    });
                } elseif ($this->strict) {
                    throw new QueryException("Invalid \$filter: relation '{$relation}' is unknown or not expandable.");
                }
                continue;
            }

            $resolved = $this->isValidFilter($column, $operator, $value, $builder);

            if ($resolved === false) {
                // Strict only rejects unknown/unfilterable properties; conditions the parser
                // could not extract or whose values the library cannot apply are skipped as before.
                if ($this->strict && $column !== '' && $this->isPropertyFilterable($column, ClassUtils::getShortName($builder->getModel())) === false) {
                    throw new QueryException("Invalid \$filter condition on '{$filterPart}'. The property is unknown or not filterable.");
                }

                continue;
            }

            $column = $resolved;

            // Handle table name qualification
            if (!str_contains((string)$column, '.')) {
                // Get all the tables involved in the query
                $query = $builder->getQuery();
                $joins = $query->joins ?? [];
                
                // If we have joins and the column exists in multiple tables, qualify it with the model's table
                if (!empty($joins)) {
                    $column = "{$modelTable}.{$column}";
                }
            }

            $this->applyFilterCondition($builder, $currentStatement, $column, $operator, $value);
        }
    }

    /**
     * Apply a validated filter condition to the query builder.
     *
     * @param Builder<TModelClass> $builder
     * @param 'where'|'orWhere' $statement
     * @param string|true $column
     * @param string|array<int, string>|null $value
     */
    private function applyFilterCondition(
        Builder $builder,
        string $statement,
        string|true $column,
        string $operator,
        string|array|null $value
    ): void {
        $normalisedOperator = strtoupper($operator);

        if (in_array($normalisedOperator, ['IN', 'NOT IN'], true)) {
            if (!is_array($value)) {
                return;
            }

            $method = $statement . ($normalisedOperator === 'NOT IN' ? 'NotIn' : 'In');
            $builder->{$method}($column, $value);
            return;
        }

        $builder->{$statement}($column, $operator, $value);
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
     * @param string|array<int<0, max>, string>|null $value
     * @param Builder<TModelClass> $builder
     * @return bool
     * @throws \ReflectionException
     */
    private function isValidFilter(string $column, string $operator, string|array|null $value, Builder $builder): string|bool
    {
        if (empty($column) || empty($operator)) {
            return false;
        }

        // Special handling for in operator
        if (in_array(strtoupper($operator), ['IN', 'NOT IN'], true)) {
            if (!is_array($value) || empty($value)) {
                return false;
            }
        } else {
            // For other operators, value should not be empty (except for 0)
            if ($value != 0 && empty($value)) {
                return false;
            }
        }

        $modelClass = get_class($builder->getModel());
        $reflection = new ReflectionClass($modelClass);
        $shortName = $reflection->getShortName();

        $filterable = $this->isPropertyFilterable($column, $shortName);

        if (is_string($filterable)) {
            return "{$builder->getModel()->getTable()}.{$filterable}";
        }

        return $filterable;
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

        /**
         * @var string $column
         * @var string $operator
         * @var string|array<int<0, max>, string> $value
         */
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
     * @return array{string, string, array<int, string>|string|null, ''|'all'|'any', string}
     * @throws \Exception
     */
    private function splitInput(string $input, bool $inverseOperator = false): array
    {
        // A leading `not` negates the expression that follows it, so drop it and invert the operator.
        if (preg_match('/^\s*not\s+(?=[A-Za-z_])(.+)$/i', $input, $notMatches)) {
            $input = $notMatches[1];
            $inverseOperator = !$inverseOperator;
        }

        // Define the regex pattern to match tokens:
        // 1. Functions and operators
        // 2. Parentheses and commas
        // 3. String literals enclosed in single quotes
        $pattern = '/\b(contains|startswith|endswith|and|or|not|eq|ne|gt|ge|lt|le|in)\b|([(),])|\'([^\']*)\''
            // 4. Numeric or date/time (anything starting with a digit).
            . '|(\d+[-+.:TZ0-9]*)'
            // 5. Boolean literals. Must precede the identifier alternative, which would otherwise swallow them.
            . '|\b(true|false)\b'
            // 6. Field names or identifiers
            . '|([A-Za-z_][A-Za-z0-9_]*)'
            . '/i';
        $lambda = '';
        $relation = '';
        // Perform global matching
        preg_match_all($pattern, $input, $matches, PREG_SET_ORDER);

        $tokens = array_map(function ($match) {
            if (!empty($match[1])) {
                // Functions and operators (e.g., contains, eq, and)
                return strtolower($match[1]);
            } elseif (!empty($match[3])) {
                // String literals without quotes
                return $match[3];
            } elseif (($match[4] ?? '') !== '') {
                // Dont't use empty here because empty('0') = true!
                $token = $match[4];
                // Numeric or date/time value.
                if (preg_match('/^\d+(\.\d+)?$/', $token, $_) && is_numeric($token)) {
                    // Numeric values
                    return $token;
                } else if (preg_match('/^\d+-\d+-\d+$/', $token, $_)) {
                    // dateValue = year "-" month "-" day
                    return $token;
                } else if (preg_match('/^\d+-\d+-\d+T\d+:\d+(:\d+(.\d+)?)?(Z|[-+]\d+:\d+)$/', $token, $_)) {
                    // dateTimeOffsetValue = year "-" month "-" day "T" hour ":" minute [ ":" second [ "." fractionalSeconds ] ] ( "Z" / sign hour ":" minute )
                    return $token;
                }

            } elseif (($match[5] ?? '') !== '') {
                // Boolean literal. Normalise to the numeric form the query builder already handles,
                // so that `eq true` behaves exactly like `eq 1` on the underlying boolean column.
                return strtolower($match[5]) === 'true' ? '1' : '0';
            } elseif (!empty($match[6])) {
                // Field names or identifiers
                return $match[6];
            }
            return null;
        }, $matches);

        $tokens = array_filter($tokens, fn($token) => $token !== null);

        if (count($tokens) < 3) {
            return ['', '', '', '', ''];
        }

        // Handle in operator
        if (isset($tokens[1]) && strtolower($tokens[1]) === 'in') {
            $column = $tokens[0];
            $operator = OperatorUtils::mapOperator($tokens[1], $inverseOperator);;
            
            // Extract values between parentheses
            $value = [];
            $inValues = array_slice($tokens, 2);
            
            foreach ($inValues as $token) {
                if ($token === '(' || $token === ')' || $token === ',') {
                    continue;
                }
                // Handle both quoted and unquoted values
                if (is_numeric($token)) {
                    $value[] = $token;
                } else {
                    $value[] = trim($token, "'");
                }
            }
            // Corrected logic: Check tokens[0] for function-based operators
        } else if (in_array($tokens[0], ['contains', 'startswith', 'endswith'], true)) {
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

            // Find the operator position - it could be at index 6 or 7
            $operatorIndex = 6;
            if (isset($tokens[7]) && OperatorUtils::isValidOperator(strtolower($tokens[7]))) {
                $operatorIndex = 7;
                $column = "{$tokens[5]}.{$tokens[6]}";
            }

            $operator = OperatorUtils::mapOperator($tokens[$operatorIndex], ($tokens[1] == 'all'));
            
            // Extract values for IN operator from the string
            if (strtolower($tokens[$operatorIndex]) === 'in') {
                $values = [];
                
                // Look for values in the remaining tokens
                $remainingTokens = array_slice($tokens, $operatorIndex);
                foreach ($remainingTokens as $token) {
                    if ($token !== ',' && $token !== '(' && $token !== ')') {
                        $values[] = trim($token, "'");
                    }
                }
                
                if (!empty($values)) {
                    $value = OperatorUtils::getValueBasedOnOperator('in', $values);
                } else {
                    throw new \Exception('Invalid IN operator syntax');
                }
            } else {
                $value = isset($tokens[$operatorIndex + 1]) ? OperatorUtils::getValueBasedOnOperator($tokens[$operatorIndex], $tokens[$operatorIndex + 1]) : '';
            }
            
            $lambda = $tokens[1];
            if ($inverseOperator) {
                // `not any(cond)` ≡ ¬∃(cond) and `not all(cond)` ≡ ∃(¬cond). The operator mapping
                // above already keys off the original quantifier, so the leading `not` only flips
                // which quantifier drives the whereHas/whereDoesntHave choice.
                $lambda = $lambda === 'any' ? 'all' : 'any';
            }
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

    /**
     * Splits an OData filter into its constituent parts, respecting nested parentheses.
     *
     * @param string $expr The OData expression to split.
     * @return array<string> The split components of the expression.
     */
    private function splitFilter(string $expr): array
    {
        // Early return if there are no parentheses or logical operators
        if (!Str::contains($expr, ['(', ')', ' and ', ' or '])) {
            // If commas are present, split by commas; otherwise, return the trimmed expression
            return Str::contains($expr, ',')
                ? array_map('trim', explode(',', $expr))
                : [trim($expr)];
        }

        $result = [];
        $current = '';
        $depth = 0;
        $length = strlen($expr);
        $i = 0;

        while ($i < $length) {
            $char = $expr[$i];

            // Handle opening parenthesis
            if ($char === '(') {
                $depth++;
            }
            // Handle closing parenthesis
            elseif ($char === ')') {
                $depth--;
            }

            // Check for logical operators at depth 0
            if ($depth === 0) {
                // Check for ' and ' (length 5) and ' or ' (length 4)
                if (substr($expr, $i, 5) === ' and ') {
                    $result[] = trim($current);
                    $result[] = 'and';
                    $current = '';
                    $i += 5;
                    continue;
                } elseif (substr($expr, $i, 4) === ' or ') {
                    $result[] = trim($current);
                    $result[] = 'or';
                    $current = '';
                    $i += 4;
                    continue;
                }
            }

            // Accumulate the current character
            $current .= $char;
            $i++;
        }

        // Add any remaining segment
        if (($current = trim($current)) !== '') {
            $result[] = $current;
        }

        return $result;
    }
}
