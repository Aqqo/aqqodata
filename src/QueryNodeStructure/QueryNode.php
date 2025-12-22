<?php

namespace Aqqo\OData\QueryNodeStructure;

abstract class QueryNode
{
    abstract public function toString(): string;
    abstract public function getLeft(): QueryNode;
    abstract public function getRight(): QueryNode;
}