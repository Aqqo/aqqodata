<?php

namespace Aqqo\OData\QueryNodeStructure;

class LambdaQueryNode extends QueryNode
{
    protected string $relation;
    protected string $lambda; // 'any' or 'all'
    protected QueryNode $condition;
    protected ?string $parameter;

    public function __construct(string $relation, string $lambda, QueryNode $condition, ?string $parameter = null)
    {
        $this->relation = $relation;
        $this->lambda = $lambda;
        $this->condition = $condition;
        $this->parameter = $parameter;
    }

    public function toString(): string
    {
        $param = $this->parameter ?? '';
        if ($param !== '') {
            $param .= ':';
        }

        return $this->relation . '/' . $this->lambda . '(' . $param . $this->condition->toString() . ')';
    }

    public function getRelation(): string
    {
        return $this->relation;
    }

    public function getLambda(): string
    {
        return $this->lambda;
    }

    public function getCondition(): QueryNode
    {
        return $this->condition;
    }

    public function getParameter(): ?string
    {
        return $this->parameter;
    }

    public function getLeft(): QueryNode
    {
        return $this->condition;
    }

    public function getRight(): QueryNode
    {
        return $this->condition;
    }
} 
