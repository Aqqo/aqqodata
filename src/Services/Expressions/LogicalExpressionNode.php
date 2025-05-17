<?php
namespace Aqqo\OData\Services\Expressions;

class LogicalExpressionNode implements ExpressionNode
{
    public function __construct(
        protected string $operator,          // 'and' or 'or'
        protected array $children
    ) {}

    public function isOr(): bool { return $this->operator === 'or'; }
    public function children(): array { return $this->children; }
}
