<?php

namespace Aqqo\OData\Tests\Unit;

use function Aqqo\OData\Tests\Feature\createQueryFromParams;
use Aqqo\OData\Tests\Testclasses\ChildTestModel;
use Aqqo\OData\Tests\Testclasses\TestModel;
use Aqqo\OData\Query;
use Illuminate\Http\Request;

describe('Nested Model Inheritance', function () {
    
    it('child model recognizes inherited relationships as expandable', function () {
        // The key test: verify that relationships from parent are recognized
        $query = Query::for(ChildTestModel::class, new Request(['$expand' => 'relatedModel']));
        
        // This should not throw an exception - the relationship should be recognized
        // even if data loading fails due to foreign key issues
        expect($query->toSql())->toBeString();
    });

    it('child model recognizes multiple inherited relationships', function () {
        // Test that multiple parent relationships are recognized
        $query = Query::for(ChildTestModel::class, new Request([
            '$expand' => 'relatedModel,relatedModels,relatedThroughPivotModels'
        ]));
        
        // All parent relationships should be recognized as expandable
        expect($query->toSql())->toBeString();
    });

    it('child model can use inherited relationships in filter expressions', function () {
        // Test that inherited relationships can be used in filter expressions
        $query = Query::for(ChildTestModel::class, new Request([
            '$filter' => "relatedModel/any(r:r/name eq 'test')"
        ]));
        
        // The relationship should be recognized in filter expressions
        expect($query->toSql())->toBeString();
    });

    it('child model can use inherited relationships with all operator', function () {
        // Test that inherited relationships work with 'all' operator
        $query = Query::for(ChildTestModel::class, new Request([
            '$filter' => "relatedModels/all(r:r/cost gt 100)"
        ]));
        
        // The relationship should be recognized
        expect($query->toSql())->toBeString();
    });

    it('child model inherits properties along with relationships', function () {
        // Test that properties are also inherited (this should already work)
        $query = Query::for(ChildTestModel::class, new Request([
            '$select' => 'name,id,description',
            '$filter' => "name eq 'test'"
        ]));
        
        // Properties should be recognized
        expect($query->toSql())->toBeString();
    });

    it('child model can use both inherited and own relationships', function () {
        // Test that child can use both parent relationships and its own
        $query = Query::for(ChildTestModel::class, new Request([
            '$expand' => 'relatedModel,childRelatedModels'
        ]));
        
        // Both should be recognized
        expect($query->toSql())->toBeString();
    });

    it('child model relationship inheritance works with complex queries', function () {
        // Test complex query combining inherited relationships
        $query = Query::for(ChildTestModel::class, new Request([
            '$select' => 'name,id',
            '$filter' => "name eq 'test' and relatedModel/any(r:r/cost gt 100)",
            '$expand' => 'relatedModel,relatedModels',
            '$orderby' => 'name asc'
        ]));
        
        // Complex query should work
        expect($query->toSql())->toBeString();
    });

    it('verifies isPropertyExpandable recognizes inherited relationships', function () {
        // Create a query to access the internal method
        $query = Query::for(ChildTestModel::class);
        
        // Use reflection to test isPropertyExpandable directly
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('isPropertyExpandable');
        $method->setAccessible(true);
        
        // Test that parent relationships are recognized as expandable
        $result1 = $method->invoke($query, 'relatedModel');
        $result2 = $method->invoke($query, 'relatedModels');
        $result3 = $method->invoke($query, 'relatedThroughPivotModels');
        
        // All parent relationships should be recognized
        expect($result1)->not()->toBeFalse()
            ->and($result2)->not()->toBeFalse()
            ->and($result3)->not()->toBeFalse();
    });

    it('child model can expand nested relationships through inherited relationships', function () {
        // Test nested expansion using inherited relationships
        $query = Query::for(ChildTestModel::class, new Request([
            '$expand' => 'relatedModel($expand=nestedRelatedModels)'
        ]));
        
        // Nested expansion should be recognized
        expect($query->toSql())->toBeString();
    });

    it('compares parent and child model expandable relationships', function () {
        // Create queries for both parent and child
        $parentQuery = Query::for(TestModel::class);
        $childQuery = Query::for(ChildTestModel::class);
        
        // Use reflection to access isPropertyExpandable
        $reflection = new \ReflectionClass($parentQuery);
        $method = $reflection->getMethod('isPropertyExpandable');
        $method->setAccessible(true);
        
        // Test that child recognizes the same relationships as parent
        $parentResult = $method->invoke($parentQuery, 'relatedModel');
        $childResult = $method->invoke($childQuery, 'relatedModel');
        
        // Both should recognize the relationship
        expect($parentResult)->not()->toBeFalse()
            ->and($childResult)->not()->toBeFalse()
            ->and($childResult)->toBe($parentResult); // Should return the same relationship name
    });
});
