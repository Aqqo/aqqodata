<?php

namespace Aqqo\OData\QueryNodeStructure;

class BasicQueryNode extends QueryNode {
    protected string $field;
    protected string $operator;
    protected mixed $value;
    protected bool $negated;

    public function __construct(string $field, string $operator, mixed $value, bool $negated = false)
    {
        $this->field = $field;
        $this->operator = $operator;
        $this->value = $value;
        $this->negated = $negated;
    }

    public function toString(): string
    {
        $prefix = $this->negated ? 'not ' : '';
        return $prefix . $this->field . ' ' . $this->operator . ' ' . $this->value;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function isNegated(): bool
    {
        return $this->negated;
    }

    public function withNegated(): self
    {
        return new self($this->field, $this->operator, $this->value, !$this->negated);
    }

    public function getLeft(): QueryNode
    {
        return $this;
    }

    public function getRight(): QueryNode
    {
        return $this;
    }
}
