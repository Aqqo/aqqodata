<?php
namespace Aqqo\OData\Services;

use Aqqo\OData\Parameters\FilterParameter;
use Aqqo\OData\Services\Expressions\ExpressionNode;
use Aqqo\OData\Services\Expressions\LogicalExpressionNode;
use Aqqo\OData\Services\Expressions\ComparisonExpressionNode;
use Aqqo\OData\QueryNodeStructure\BasicQueryNode;
use Aqqo\OData\QueryNodeStructure\CompositeQueryNode;
use Aqqo\OData\QueryNodeStructure\QueryNode;

class FilterParser
{
    /**
     * @param  string $filter
     * @return QueryNode  // your own AST root interface
     */
    public function parse(string $filter): QueryNode
    {
        // 1. strip outer parentheses
        // $filter = $this->stripParentheses($filter); // thuis might not be needed since it fucks with our parsing

        // 2. detect nested vs simple


        $queryNode = $this->splitFilter($filter);

        return $this->splitFilter($filter);
    }

    private function stripParentheses(string $value): string
    {
        return (str_starts_with($value, '(') && str_ends_with($value, ')'))
            ? substr($value, 1, -1)
            : $value;
    }

    /**
     * Splits an OData filter into its constituent parts and returns either a BasicQueryNode or CompositeQueryNode.
     *
     * @param string $expr The OData expression to split.
     * @return QueryNode The parsed query node.
     */
    private function splitFilter(string $expr): QueryNode
    {
        // If no operators or parentheses, return as BasicQueryNode
        if (!str_contains($expr, '(') && !str_contains($expr, ')') && !preg_match('/\s(and|or)\s/i', $expr)) {
            return $this->createBasicNode(trim($expr));
        }

        // First pass - find minimum depth where operators occur
        $minOperatorDepth = PHP_INT_MAX;
        $tempDepth = 0;
        $length = strlen($expr);
        
        for ($j = 0; $j < $length; $j++) {
            if ($expr[$j] === '(') {
                $tempDepth++;
            } elseif ($expr[$j] === ')') {
                $tempDepth--;
            }
            
            // Check for operators at current depth
            if (substr($expr, $j, 5) === ' and ' || substr($expr, $j, 4) === ' or ') {
                $minOperatorDepth = min($minOperatorDepth, $tempDepth);
            }
        }

        // If we found no operators, try to parse as a basic node
        if ($minOperatorDepth === PHP_INT_MAX) {
            return $this->createBasicNode($expr);
        }

        $result = [];
        $current = '';
        $depth = 0;
        $i = 0;

        // Second pass - only split at minimum depth
        while ($i < $length) {
            $char = $expr[$i];

            if ($char === '(') {
                $depth++;
                if ($depth === 1) {
                    // Start collecting content inside parentheses
                    $current = '';
                } else {
                    $current .= $char;
                }
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    // We've closed a complete parenthesized expression
                    if (($current = trim($current)) !== '') {
                        $result[] = $this->splitFilter($current);
                    }
                    $current = '';
                } else {
                    $current .= $char;
                }
            } else {
                // Only process operators at minimum depth
                if ($depth === $minOperatorDepth) {
                    if (substr($expr, $i, 5) === ' and ') {
                        if (($current = trim($current)) !== '') {
                            $result[] = $this->splitFilter($current);
                        }
                        $result[] = 'and';
                        $current = '';
                        $i += 5;
                        continue;
                    } elseif (substr($expr, $i, 4) === ' or ') {
                        if (($current = trim($current)) !== '') {
                            $result[] = $this->splitFilter($current);
                        }
                        $result[] = 'or';
                        $current = '';
                        $i += 4;
                        continue;
                    }
                }
                $current .= $char;
            }
            $i++;
        }

        // Add the last constituent if it exists
        if (($current = trim($current)) !== '') {
            $result[] = $this->splitFilter($current);
        }

        // If we have only one element, return it directly
        if (count($result) === 1) {
            return $result[0];
        }

        // If we have an even number of elements, something is wrong
        if (count($result) % 2 === 0) {
            throw new \InvalidArgumentException('Invalid filter expression: missing constituent or operator');
        }

        // Build the composite node
        $left = array_shift($result);
        while (!empty($result)) {
            $operator = array_shift($result);
            $right = array_shift($result);
            $left = new CompositeQueryNode($left, $operator, $right);
        }

        return $left;
    }

    public function findDepth(string $expr): int
    {
        $depth = 0;
        $maxDepth = 0;
        $length = strlen($expr);
        for ($i = 0; $i < $length; $i++) {
            if ($expr[$i] === '(') {
                $depth++;
                $maxDepth = max($maxDepth, $depth);
            } elseif ($expr[$i] === ')') {
                $depth--;
            }
        }
        return $maxDepth;
    }

    /**
     * Creates a BasicQueryNode from a simple expression.
     *
     * @param string $expr The expression to parse
     * @return BasicQueryNode
     */
    private function createBasicNode(string $expr): BasicQueryNode
    {
        // Handle lambda expressions (any/all)
        if (preg_match('/^([^\/]+)\/(any|all)\(([^:]+):(.+)\)$/', $expr, $matches)) {
            $relation = $matches[1];
            $lambda = $matches[2];
            $param = $matches[3];
            $condition = $matches[4];
            
            // Parse the condition inside the lambda
            $innerNode = $this->createBasicNode($condition);
            return new BasicQueryNode($relation, $lambda, $innerNode);
        }

        // Handle basic comparison expressions
        if (preg_match('/^(.+?)\s+(eq|ne|gt|ge|lt|le|in|contains|startswith|endswith)\s+(.+)$/i', $expr, $matches)) {
            $field = trim($matches[1]);
            $operator = strtolower($matches[2]);
            $value = trim($matches[3]);

            // Handle other operators
            $value = trim($value, "'");
            return new BasicQueryNode($field, $operator, $value);
        }

        return new BasicQueryNode($expr, 'eq', 'true');
    }
}
