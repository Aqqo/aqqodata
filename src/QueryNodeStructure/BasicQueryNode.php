<?php

namespace Aqqo\OData\QueryNodeStructure;

class BasicQueryNode extends QueryNode {
    protected string $field;
    protected string $operator;
    protected $value;

    public function __construct(string $field, string $operator, $value)
    {
        $this->field = $field;
        $this->operator = $operator;
        $this->value = $value;
    }

    public function toString(): string
    {
       return $this->field . ' ' . $this->operator . ' ' . $this->value;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getValue()
    {
        return $this->value;
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