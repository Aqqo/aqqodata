<?php

namespace Aqqo\OData\Filters;

use Aqqo\OData\Utils\OperatorUtils;
use Illuminate\Support\Str;

/**
 * Parser for OData filter strings
 */
class FilterParser
{
    /**
     * Parse an OData filter string into filter objects
     *
     * @param string $filterString
     * @return FilterInterface|null
     */
    public function parse(string $filterString): ?FilterInterface
    {
        $filterString = trim($filterString);
        
        if (empty($filterString)) {
            return null;
        }

        // Remove outer parentheses if they wrap the entire expression
        $filterString = $this->stripOuterParentheses($filterString);

        // Check for logical operators at the top level
        if ($this->hasTopLevelLogicalOperator($filterString)) {
            return $this->parseLogicalExpression($filterString);
        }

        // Check for function-based filters
        if ($this->isFunctionFilter($filterString)) {
            return $this->parseFunctionFilter($filterString);
        }

        // Check for relationship filters
        if ($this->isRelationshipFilter($filterString)) {
            return $this->parseRelationshipFilter($filterString);
        }

        // Parse as simple filter
        return $this->parseSimpleFilter($filterString);
    }

    /**
     * Check if the filter has a top-level logical operator
     *
     * @param string $filterString
     * @return bool
     */
    private function hasTopLevelLogicalOperator(string $filterString): bool
    {
        $depth = 0;
        $length = strlen($filterString);

        for ($i = 0; $i < $length; $i++) {
            $char = $filterString[$i];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($depth === 0) {
                // Check for ' and ' or ' or ' at depth 0
                if (substr($filterString, $i, 5) === ' and ') {
                    return true;
                } elseif (substr($filterString, $i, 4) === ' or ') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Parse a logical expression (AND/OR)
     *
     * @param string $filterString
     * @return LogicalFilter
     */
    private function parseLogicalExpression(string $filterString): LogicalFilter
    {
        $parts = $this->splitByLogicalOperator($filterString);
        $operator = $parts['operator'];
        $expressions = $parts['expressions'];

        $logicalFilter = new LogicalFilter($operator);

        foreach ($expressions as $expression) {
            $subFilter = $this->parse(trim($expression));
            if ($subFilter) {
                $logicalFilter->addFilter($subFilter);
            }
        }

        return $logicalFilter;
    }

    /**
     * Split a filter string by logical operators
     *
     * @param string $filterString
     * @return array{operator: string, expressions: array<string>}
     */
    private function splitByLogicalOperator(string $filterString): array
    {
        $expressions = [];
        $current = '';
        $depth = 0;
        $length = strlen($filterString);
        $operator = 'and'; // default

        for ($i = 0; $i < $length; $i++) {
            $char = $filterString[$i];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($depth === 0) {
                if (substr($filterString, $i, 5) === ' and ') {
                    if (!empty($current)) {
                        $expressions[] = trim($current);
                    }
                    $operator = 'and';
                    $current = '';
                    $i += 4;
                    continue;
                } elseif (substr($filterString, $i, 4) === ' or ') {
                    if (!empty($current)) {
                        $expressions[] = trim($current);
                    }
                    $operator = 'or';
                    $current = '';
                    $i += 3;
                    continue;
                }
            }

            $current .= $char;
        }

        if (!empty($current)) {
            $expressions[] = trim($current);
        }

        return [
            'operator' => $operator,
            'expressions' => $expressions
        ];
    }

    /**
     * Check if the filter is a function-based filter
     *
     * @param string $filterString
     * @return bool
     */
    private function isFunctionFilter(string $filterString): bool
    {
        $functions = ['contains', 'startswith', 'endswith'];
        
        foreach ($functions as $function) {
            if (Str::startsWith($filterString, $function . '(')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse a function-based filter
     *
     * @param string $filterString
     * @return FunctionFilter
     */
    private function parseFunctionFilter(string $filterString): FunctionFilter
    {
        // Extract function name
        $functionEnd = strpos($filterString, '(');
        $function = substr($filterString, 0, $functionEnd);
        
        // Extract parameters
        $paramsStart = $functionEnd + 1;
        $paramsEnd = strrpos($filterString, ')');
        $paramsString = substr($filterString, $paramsStart, $paramsEnd - $paramsStart);
        
        $params = $this->parseFunctionParameters($paramsString);
        
        if (count($params) >= 2) {
            return new FunctionFilter($function, $params[0], $params[1]);
        }

        throw new \InvalidArgumentException("Invalid function filter: {$filterString}");
    }

    /**
     * Parse function parameters
     *
     * @param string $paramsString
     * @return array<string>
     */
    private function parseFunctionParameters(string $paramsString): array
    {
        $params = [];
        $current = '';
        $depth = 0;
        $inQuotes = false;
        $length = strlen($paramsString);

        for ($i = 0; $i < $length; $i++) {
            $char = $paramsString[$i];

            if ($char === "'" && ($i === 0 || $paramsString[$i - 1] !== '\\')) {
                $inQuotes = !$inQuotes;
            }

            if (!$inQuotes && $char === '(') {
                $depth++;
            } elseif (!$inQuotes && $char === ')') {
                $depth--;
            }

            if (!$inQuotes && $depth === 0 && $char === ',') {
                $params[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (!empty($current)) {
            $params[] = trim($current);
        }

        return array_map(fn($param) => trim($param, "'"), $params);
    }

    /**
     * Check if the filter is a relationship filter
     *
     * @param string $filterString
     * @return bool
     */
    private function isRelationshipFilter(string $filterString): bool
    {
        return Str::startsWith($filterString, 'any(') || Str::startsWith($filterString, 'all(');
    }

    /**
     * Parse a relationship filter
     *
     * @param string $filterString
     * @return RelationshipFilter
     */
    private function parseRelationshipFilter(string $filterString): RelationshipFilter
    {
        // Extract function name (any/all)
        $functionEnd = strpos($filterString, '(');
        $function = substr($filterString, 0, $functionEnd);
        
        // Extract parameters
        $paramsStart = $functionEnd + 1;
        $paramsEnd = strrpos($filterString, ')');
        $paramsString = substr($filterString, $paramsStart, $paramsEnd - $paramsStart);
        
        $params = $this->parseFunctionParameters($paramsString);
        
        if (count($params) >= 2) {
            $relation = $params[0];
            $condition = $this->parse($params[1]);
            
            if ($condition) {
                return new RelationshipFilter($function, $relation, $condition);
            }
        }

        throw new \InvalidArgumentException("Invalid relationship filter: {$filterString}");
    }

    /**
     * Parse a simple filter
     *
     * @param string $filterString
     * @return SimpleFilter
     */
    private function parseSimpleFilter(string $filterString): SimpleFilter
    {
        // Use the same tokenization logic as the original FilterTrait
        $tokens = $this->tokenizeAdvanced($filterString);
        
        if (count($tokens) < 3) {
            throw new \InvalidArgumentException("Invalid simple filter: {$filterString}");
        }

        $column = $tokens[0];
        $operator = $tokens[1];
        $value = $tokens[2];

        // Map OData operators to SQL operators
        $operator = $this->mapOperator($operator);

        // Handle IN operator
        if (strtolower($operator) === 'in') {
            $values = $this->parseInValues($value);
            return new SimpleFilter($column, $operator, $values);
        }

        return new SimpleFilter($column, $operator, $value);
    }

    /**
     * Advanced tokenization similar to the original FilterTrait
     *
     * @param string $input
     * @return array<string>
     */
    private function tokenizeAdvanced(string $input): array
    {
        // Define the regex pattern to match tokens:
        // 1. Functions and operators
        // 2. Parentheses and commas
        // 3. String literals enclosed in single quotes
        // 4. Numeric values
        // 5. Field names or identifiers
        $pattern = '/\b(contains|startswith|endswith|and|or|not|eq|ne|gt|ge|lt|le|in)\b|([(),])|\'([^\']*)\'|(\d+(\.\d+)?)|([A-Za-z_][A-Za-z0-9_]*)/i';
        
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

        return array_filter($tokens, fn($token) => $token !== null);
    }

    /**
     * Map OData operators to SQL operators
     *
     * @param string $odataOperator
     * @return string
     */
    private function mapOperator(string $odataOperator): string
    {
        $operatorMap = [
            'eq' => '=',
            'ne' => '!=',
            'gt' => '>',
            'ge' => '>=',
            'lt' => '<',
            'le' => '<=',
            'in' => 'IN',
        ];

        return $operatorMap[strtolower($odataOperator)] ?? $odataOperator;
    }

    /**
     * Tokenize a filter string
     *
     * @param string $filterString
     * @return array<string>
     */
    private function tokenize(string $filterString): array
    {
        $pattern = '/\b(contains|startswith|endswith|and|or|not|eq|ne|gt|ge|lt|le|in)\b|([(),])|\'([^\']*)\'|(\d+(\.\d+)?)|([A-Za-z_][A-Za-z0-9_]*)/i';
        preg_match_all($pattern, $filterString, $matches, PREG_SET_ORDER);

        $tokens = array_map(function ($match) {
            if (!empty($match[1])) {
                return strtolower($match[1]);
            } elseif (!empty($match[3])) {
                return $match[3];
            } elseif (isset($match[4]) && is_numeric($match[4])) {
                return $match[4];
            } elseif (!empty($match[6])) {
                return $match[6];
            }
            return null;
        }, $matches);

        return array_filter($tokens, fn($token) => $token !== null);
    }

    /**
     * Parse IN operator values
     *
     * @param string $value
     * @return array<string>
     */
    private function parseInValues(string $value): array
    {
        preg_match("/\((.*?)\)/", $value, $matches);
        if (isset($matches[1])) {
            return array_map(function($val) {
                return trim($val, "'");
            }, explode(',', $matches[1]));
        }
        return [];
    }

    /**
     * Strip outer parentheses from a string
     *
     * @param string $value
     * @return string
     */
    private function stripOuterParentheses(string $value): string
    {
        if (Str::startsWith($value, '(') && Str::endsWith($value, ')')) {
            $depth = 0;
            $length = strlen($value);
            
            for ($i = 0; $i < $length; $i++) {
                if ($value[$i] === '(') {
                    $depth++;
                } elseif ($value[$i] === ')') {
                    $depth--;
                    if ($depth === 0 && $i < $length - 1) {
                        return $value; // Not balanced, return as is
                    }
                }
            }
            
            if ($depth === 0) {
                return substr($value, 1, -1);
            }
        }
        
        return $value;
    }
}
