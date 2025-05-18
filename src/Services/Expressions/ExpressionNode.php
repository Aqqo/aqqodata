<?php
namespace Aqqo\OData\Services\Expressions;

/**
 * Interface for all expression nodes in the filter AST.
 * This is the base interface that all filter expressions must implement.
 */
interface ExpressionNode
{
    /**
     * Check if this is an OR expression node.
     * 
     * @return bool True if this is an OR expression, false otherwise
     */
    public function isOr(): bool;
}
