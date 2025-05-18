<?php
namespace Aqqo\OData\Services\Expressions;

/**
 * Represents a logical expression node (AND/OR) in the filter AST.
 * This node can contain multiple child expressions that are combined using the specified operator.
 */
class LogicalExpressionNode implements ExpressionNode
{
    /**
     * @param string $operator The logical operator ('and' or 'or')
     * @param array<ExpressionNode> $children Child expression nodes
     */
    public function __construct(
        private readonly string $operator,
        private readonly array $children
    ) {}

    /**
     * Check if this is an OR expression node.
     * 
     * @return bool True if this is an OR expression, false otherwise
     */
    public function isOr(): bool 
    { 
        return strtolower($this->operator) === 'or'; 
    }

    /**
     * Get the child expression nodes.
     * 
     * @return array<ExpressionNode> The child expressions
     */
    public function children(): array 
    { 
        return $this->children; 
    }
}
