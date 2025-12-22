<?php

namespace Aqqo\OData\QueryNodeStructure;

class NotQueryNode extends QueryNode
{
    public function __construct(private QueryNode $inner)
    {
    }

    public function getInner(): QueryNode
    {
        return $this->inner;
    }

    public function toString(): string
    {
        return 'not(' . $this->inner->toString() . ')';
    }

    public function getLeft(): QueryNode
    {
        return $this->inner;
    }

    public function getRight(): QueryNode
    {
        return $this->inner;
    }
}
