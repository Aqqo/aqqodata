<?php
namespace Aqqo\OData\Services\Expressions;

use Aqqo\OData\Parameters\FilterParameter;

class ComparisonExpressionNode implements ExpressionNode
{
    public function __construct(protected FilterParameter $param) {}

    public function isOr(): bool { return false; }
    public function parameter(): FilterParameter { return $this->param; }
}
