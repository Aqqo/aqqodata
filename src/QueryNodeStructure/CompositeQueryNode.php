<?php

namespace Aqqo\OData\QueryNodeStructure;

class CompositeQueryNode extends QueryNode
{
    protected QueryNode $left;
    protected string $operator; // 'AND', 'OR'
    protected QueryNode $right;
    protected bool $grouped;

    public function __construct(QueryNode $left, string $operator, QueryNode $right, bool $grouped = false)
    {
        $this->left = $left;
        $this->operator = $operator;
        $this->right = $right;
        $this->grouped = $grouped;
    }

    public function toString(): string
    {
        return '(' . $this->left->toString() . " {$this->operator} " . $this->right->toString() . ')';
    }

    public function getLeft(): QueryNode
    {
        return $this->left;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getRight(): QueryNode
    {
        return $this->right;
    }

    public function getChildren(): array
    {
        return [$this->left, $this->operator, $this->right];
    }

    public function isGrouped(): bool
    {
        return $this->grouped;
    }

    public function withGrouped(bool $grouped = true): self
    {
        return new self($this->left, $this->operator, $this->right, $grouped);
    }
}
