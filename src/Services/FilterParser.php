<?php
namespace Aqqo\OData\Services;

use Aqqo\OData\Parameters\FilterParameter;
use Aqqo\OData\Services\Expressions\ExpressionNode;
use Aqqo\OData\Services\Expressions\LogicalExpressionNode;
use Aqqo\OData\Services\Expressions\ComparisonExpressionNode;

class FilterParser
{
    /**
     * @param  string $filter
     * @return ExpressionNode  // your own AST root interface
     */
    public function parse(string $filter): ExpressionNode
    {
        // 1. strip outer parentheses
        $filter = $this->stripParentheses($filter);

        // 2. detect nested vs simple
        $tokens = $this->splitFilter($filter);

        if (count($tokens) === 1) {
            // 4. or return ComparisonExpressionNode wrapping FilterParameter::fromString()
            return new ComparisonExpressionNode(FilterParameter::fromString($tokens[0]));
        }

        // 3. build LogicalExpressionNode (AND/OR) with children
        $children = [];
        $operator = null;
        foreach ($tokens as $token) {
            if (strtolower($token) === 'and' || strtolower($token) === 'or') {
                $operator = strtolower($token);
                continue;
            }
            $children[] = $this->parse($token);
        }
        return new LogicalExpressionNode($operator ?? 'and', $children);
    }

    private function stripParentheses(string $value): string
    {
        return (str_starts_with($value, '(') && str_ends_with($value, ')'))
            ? substr($value, 1, -1)
            : $value;
    }

    /**
     * Splits an OData filter into its constituent parts, respecting nested parentheses.
     *
     * @param string $expr The OData expression to split.
     * @return array<string> The split components of the expression.
     */
    private function splitFilter(string $expr): array
    {
        if (!str_contains($expr, '(') && !str_contains($expr, ')') && !preg_match('/\s(and|or)\s/i', $expr)) {
            return [trim($expr)];
        }

        $result = [];
        $current = '';
        $depth = 0;
        $length = strlen($expr);
        $i = 0;

        while ($i < $length) {
            $char = $expr[$i];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($depth === 0) {
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

            $current .= $char;
            $i++;
        }

        if (($current = trim($current)) !== '') {
            $result[] = $current;
        }

        return $result;
    }
}
