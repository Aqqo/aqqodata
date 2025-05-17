<?php
namespace Aqqo\OData\Services\Expressions;

interface ExpressionNode
{
    public function isOr(): bool;
}
