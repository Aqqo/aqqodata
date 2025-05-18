<?php
namespace Aqqo\OData\Services\Expressions;

use Aqqo\OData\Parameters\FilterParameter;

/**
 * Represents a comparison expression node in the filter AST.
 * This node contains a single filter parameter that defines the comparison operation.
 */
class ComparisonExpressionNode implements ExpressionNode
{
    /**
     * @param FilterParameter $param The filter parameter containing the comparison details
     */
    public function __construct(
        private readonly FilterParameter $param
    ) {}

    /**
     * Check if this is an OR expression node.
     * Comparison nodes are never OR expressions.
     * 
     * @return bool Always returns false
     */
    public function isOr(): bool 
    { 
        return false; 
    }

    /**
     * Get the filter parameter for this comparison.
     * 
     * @return FilterParameter The filter parameter
     */
    public function parameter(): FilterParameter 
    { 
        return $this->param; 
    }
}
