<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\QueryNodeStructure\BasicQueryNode;
use Aqqo\OData\QueryNodeStructure\CompositeQueryNode;
use Aqqo\OData\QueryNodeStructure\LambdaQueryNode;
use Aqqo\OData\QueryNodeStructure\NotQueryNode;

describe('QueryNodes', function () {
    it('tests BasicQueryNode', function () {
        $node = new BasicQueryNode('name', 'eq', 'test', false);
        expect($node->getField())->toBe('name');
        expect($node->getOperator())->toBe('eq');
        expect($node->getValue())->toBe('test');
        expect($node->isNegated())->toBeFalse();
        expect($node->toString())->toBe('name eq test');
        expect($node->getLeft())->toBe($node);
        expect($node->getRight())->toBe($node);

        $negated = $node->withNegated();
        expect($negated->isNegated())->toBeTrue();
        expect($negated->toString())->toBe('not name eq test');
    });

    it('tests CompositeQueryNode', function () {
        $left = new BasicQueryNode('a', 'eq', '1');
        $right = new BasicQueryNode('b', 'eq', '2');
        $node = new CompositeQueryNode($left, 'and', $right, false);

        expect($node->getLeft())->toBe($left);
        expect($node->getOperator())->toBe('and');
        expect($node->getRight())->toBe($right);
        expect($node->isGrouped())->toBeFalse();
        expect($node->toString())->toBe('(a eq 1 and b eq 2)');
        expect($node->getChildren())->toBe([$left, 'and', $right]);

        $grouped = $node->withGrouped(true);
        expect($grouped->isGrouped())->toBeTrue();
    });

    it('tests LambdaQueryNode', function () {
        $condition = new BasicQueryNode('x', 'eq', 'y');
        $node = new LambdaQueryNode('items', 'any', $condition, 'p');

        expect($node->getRelation())->toBe('items');
        expect($node->getLambda())->toBe('any');
        expect($node->getCondition())->toBe($condition);
        expect($node->getParameter())->toBe('p');
        expect($node->toString())->toBe('items/any(p:x eq y)');
        expect($node->getLeft())->toBe($condition);
        expect($node->getRight())->toBe($condition);

        $noParam = new LambdaQueryNode('items', 'all', $condition);
        expect($noParam->getParameter())->toBeNull();
        expect($noParam->toString())->toBe('items/all(x eq y)');
    });

    it('tests NotQueryNode', function () {
        $inner = new BasicQueryNode('a', 'eq', 'b');
        $node = new NotQueryNode($inner);

        expect($node->getInner())->toBe($inner);
        expect($node->toString())->toBe('not(a eq b)');
        expect($node->getLeft())->toBe($inner);
        expect($node->getRight())->toBe($inner);
    });
});
